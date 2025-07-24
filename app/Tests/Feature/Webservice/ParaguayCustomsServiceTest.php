<?php

namespace Tests\Feature\Webservice;

use Tests\TestCase;
use App\Models\Company;
use App\Models\Voyage;
use App\Models\Vessel;
use App\Models\Shipment;
use App\Models\Container;
use App\Models\User;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceLog;
use App\Models\WebserviceResponse;
use App\Services\Webservice\ParaguayCustomsService;
use App\Services\Webservice\SoapClientService;
use App\Services\Webservice\XmlSerializerService;
use App\Services\Webservice\CertificateManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use SoapClient;
use SoapFault;
use Carbon\Carbon;

/**
 * MÓDULO 4: WEBSERVICES ADUANA - Test ParaguayCustomsService
 * 
 * Test unitario completo para webservices Paraguay GDSF/DNA
 * Usa datos reales de PARANA.csv para validación integral
 * 
 * DATOS REALES UTILIZADOS (PARANA.csv):
 * - Empresa: MAERSK LINE ARGENTINA S.A.
 * - Barcaza: PAR13001, Voyage: V022NB
 * - Ruta: ARBUE → PYTVT (Buenos Aires → Terminal Villeta Paraguay)
 * - Contenedores: 40HC, 20GP con datos reales del manifiesto
 * - 253 filas × 73 columnas, 111 conocimientos embarque
 * 
 * COBERTURA DE PRUEBAS:
 * ✅ sendImportManifest() - Éxito y errores
 * ✅ queryManifestStatus() - Estados válidos
 * ✅ rectifyManifest() - Rectificaciones
 * ✅ Validaciones pre-envío robustas
 * ✅ Generación XML Paraguay específico
 * ✅ Integración base de datos completa
 * ✅ Manejo errores SOAP y timeouts
 * ✅ Parsing respuestas GDSF Paraguay
 */
class ParaguayCustomsServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private ParaguayCustomsService $paraguayService;
    private Company $testCompany;
    private User $testUser;
    private Voyage $testVoyage;
    private Vessel $testVessel;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear datos de prueba basados en PARANA.csv
        $this->createTestData();
        
        // Mock servicios externos
        $this->mockExternalServices();
        
        // Inicializar servicio Paraguay
        $this->paraguayService = new ParaguayCustomsService($this->testCompany);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Crear datos de prueba basados en PARANA.csv
     */
    private function createTestData(): void
    {
        // Empresa basada en datos reales PARANA.csv
        $this->testCompany = Company::create([
            'name' => 'MAERSK LINE ARGENTINA S.A.',
            'tax_id' => '30-50000000-7', // CUIT Argentina formato válido
            'address' => 'Buenos Aires, Argentina',
            'active' => true,
            'webservice_config' => [
                'paraguay' => [
                    'environment' => 'testing',
                    'certificate_path' => 'certificates/maersk_py.p12',
                    'certificate_password' => 'test_password',
                ],
            ],
        ]);

        // Usuario de prueba
        $this->testUser = User::create([
            'name' => 'Test User Paraguay',
            'email' => 'test.paraguay@maersk.com',
            'password' => bcrypt('password'),
            'company_id' => $this->testCompany->id,
        ]);

        // Embarcación basada en PARANA.csv
        $this->testVessel = Vessel::create([
            'name' => 'PAR13001',
            'imo_number' => 'PAR13001',
            'flag' => 'AR',
            'capacity_teu' => 48, // Datos reales según análisis
            'is_barge' => true,
            'active' => true,
        ]);

        // Viaje basado en PARANA.csv
        $this->testVoyage = Voyage::create([
            'voyage_number' => 'V022NB',
            'vessel_id' => $this->testVessel->id,
            'company_id' => $this->testCompany->id,
            'departure_port' => 'ARBUE', // Buenos Aires
            'arrival_port' => 'PYTVT',   // Paraguay Terminal Villeta
            'departure_date' => now()->addDays(1),
            'estimated_arrival' => now()->addDays(3),
            'status' => 'planned',
        ]);

        // Crear shipments y containers basados en datos reales
        $this->createRealisticShipmentsAndContainers();
    }

    /**
     * Crear shipments y containers realistas basados en PARANA.csv
     */
    private function createRealisticShipmentsAndContainers(): void
    {
        // Datos realistas basados en análisis PARANA.csv
        $containerTypes = ['40HC', '20GP', '40GP', '20OT'];
        $shipperNames = [
            'CARGILL S.A.C.I.',
            'BUNGE ARGENTINA S.A.',
            'DREYFUS COMMODITIES S.A.',
            'ADM AGRO S.A.',
        ];
        $consigneeNames = [
            'TERMINAL VILLETA S.A.',
            'LOGÍSTICA HIDROVÍA S.R.L.',
            'CARGOPACK PARAGUAY S.A.',
            'IMPORTADORA ASUNCIÓN S.A.',
        ];

        // Crear 5 shipments representativos (de los 111 reales)
        for ($i = 1; $i <= 5; $i++) {
            $shipment = Shipment::create([
                'voyage_id' => $this->testVoyage->id,
                'bl_number' => sprintf('MAER%06d', $i),
                'shipper_name' => $this->faker->randomElement($shipperNames),
                'consignee_name' => $this->faker->randomElement($consigneeNames),
                'description' => 'SOYBEANS IN BULK - SOJA EN GRANO A GRANEL',
                'gross_weight' => $this->faker->numberBetween(15000, 25000),
                'volume' => $this->faker->numberBetween(800, 1200),
                'number_of_packages' => 1, // Granel
                'status' => 'confirmed',
            ]);

            // Crear 1-3 contenedores por shipment (promedio 2.3 según análisis)
            $numContainers = $this->faker->numberBetween(1, 3);
            for ($j = 1; $j <= $numContainers; $j++) {
                Container::create([
                    'shipment_id' => $shipment->id,
                    'container_number' => sprintf('HASU%07d', ($i * 1000) + $j),
                    'container_type' => $this->faker->randomElement($containerTypes),
                    'seal_number' => sprintf('GW%06d', $this->faker->numberBetween(100000, 999999)),
                    'gross_weight' => $this->faker->numberBetween(8000, 12000),
                    'tare_weight' => $this->faker->numberBetween(2000, 4000),
                    'is_empty' => false,
                    'status' => 'loaded',
                ]);
            }
        }
    }

    /**
     * Mock servicios externos
     */
    private function mockExternalServices(): void
    {
        // Mock SoapClientService
        $this->app->singleton(SoapClientService::class, function () {
            return Mockery::mock(SoapClientService::class);
        });

        // Mock CertificateManagerService
        $this->app->singleton(CertificateManagerService::class, function () {
            $mock = Mockery::mock(CertificateManagerService::class);
            $mock->shouldReceive('hasValidCertificate')
                 ->with('PY')
                 ->andReturn(true);
            return $mock;
        });
    }

    /** @test */
    public function can_send_import_manifest_successfully()
    {
        // Arrange
        $mockSoapClient = Mockery::mock(SoapClient::class);
        
        // Mock respuesta exitosa Paraguay GDSF
        $successfulResponse = (object) [
            'xml' => '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                    <soap:Body>
                        <RegistrarManifiestoResponse>
                            <statusCode>OK</statusCode>
                            <id>PY2025000001</id>
                            <MessageHeaderDocument>
                                <ram:ID>15000TEMF000001C</ram:ID>
                            </MessageHeaderDocument>
                        </RegistrarManifiestoResponse>
                    </soap:Body>
                </soap:Envelope>'
        ];

        $mockSoapClient->shouldReceive('__soapCall')
                      ->once()
                      ->with('enviarManifiesto', Mockery::any(), null, Mockery::any())
                      ->andReturn($successfulResponse);

        $this->app->instance(SoapClient::class, $mockSoapClient);

        // Act
        $result = $this->paraguayService->sendImportManifest($this->testVoyage, $this->testUser->id);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('manifest_reference', $result);
        $this->assertArrayHasKey('paraguay_reference', $result);
        $this->assertEquals('PY2025000001', $result['paraguay_reference']);

        // Verificar registro en base de datos
        $this->assertDatabaseHas('webservice_transactions', [
            'voyage_id' => $this->testVoyage->id,
            'company_id' => $this->testCompany->id,
            'webservice_type' => 'manifiesto',
            'country' => 'PY',
            'status' => 'success',
        ]);

        // Verificar respuesta guardada
        $this->assertDatabaseHas('webservice_responses', [
            'paraguay_gdsf_reference' => 'PY2025000001',
            'customs_status' => 'RECEIVED',
        ]);

        // Verificar logs creados
        $this->assertDatabaseHas('webservice_logs', [
            'level' => 'info',
            'message' => 'Iniciando envío Manifiesto Paraguay',
        ]);
    }

    /** @test */
    public function can_handle_soap_fault_errors()
    {
        // Arrange
        $mockSoapClient = Mockery::mock(SoapClient::class);
        
        $soapFault = new SoapFault('Server.Timeout', 'Timeout en el servidor Paraguay');
        
        $mockSoapClient->shouldReceive('__soapCall')
                      ->once()
                      ->andThrow($soapFault);

        $this->app->instance(SoapClient::class, $mockSoapClient);

        // Act
        $result = $this->paraguayService->sendImportManifest($this->testVoyage, $this->testUser->id);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('SOAP_FAULT', $result['error_code']);
        $this->assertStringContains('Timeout en el servidor Paraguay', $result['error_message']);
        $this->assertTrue($result['can_retry']); // Server.Timeout es reintentar

        // Verificar error registrado
        $this->assertDatabaseHas('webservice_transactions', [
            'voyage_id' => $this->testVoyage->id,
            'status' => 'error',
            'error_code' => 'SOAP_FAULT',
        ]);

        // Verificar log de error
        $this->assertDatabaseHas('webservice_logs', [
            'level' => 'error',
            'message' => 'SOAP Fault Paraguay',
        ]);
    }

    /** @test */
    public function can_query_manifest_status()
    {
        // Arrange
        $paraguayReference = 'PY2025000001';
        
        $mockSoapClient = Mockery::mock(SoapClient::class);
        
        $statusResponse = (object) [
            'xml' => '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                    <soap:Body>
                        <ConsultarEstadoResponse>
                            <statusCode>PROCESADO</statusCode>
                            <detalles>Manifiesto procesado exitosamente</detalles>
                        </ConsultarEstadoResponse>
                    </soap:Body>
                </soap:Envelope>'
        ];

        $mockSoapClient->shouldReceive('__soapCall')
                      ->once()
                      ->with('consultarEstado', Mockery::any(), null, Mockery::any())
                      ->andReturn($statusResponse);

        $this->app->instance(SoapClient::class, $mockSoapClient);

        // Act
        $result = $this->paraguayService->queryManifestStatus($paraguayReference);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('PROCESADO', $result['status']);
        $this->assertEquals('Manifiesto procesado exitosamente', $result['status_description']);
        $this->assertArrayHasKey('last_update', $result);
    }

    /** @test */
    public function can_rectify_manifest()
    {
        // Arrange
        $paraguayReference = 'PY2025000001';
        $corrections = [
            'gross_weight' => '25000.00',
            'consignee_name' => 'NUEVO CONSIGNATARIO S.A.',
        ];
        
        $mockSoapClient = Mockery::mock(SoapClient::class);
        
        $rectifyResponse = (object) [
            'xml' => '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                    <soap:Body>
                        <RectificarManifiestoResponse>
                            <statusCode>OK</statusCode>
                            <id>PY2025000001R001</id>
                        </RectificarManifiestoResponse>
                    </soap:Body>
                </soap:Envelope>'
        ];

        $mockSoapClient->shouldReceive('__soapCall')
                      ->once()
                      ->with('rectificarManifiesto', Mockery::any(), null, Mockery::any())
                      ->andReturn($rectifyResponse);

        $this->app->instance(SoapClient::class, $mockSoapClient);

        // Act
        $result = $this->paraguayService->rectifyManifest($paraguayReference, $corrections);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('rectification_reference', $result);
        $this->assertEquals('PY2025000001R001', $result['rectification_reference']);
    }

    /** @test */
    public function validates_voyage_data_before_sending()
    {
        // Arrange - Crear viaje con datos incompletos
        $invalidVoyage = Voyage::create([
            'voyage_number' => null, // Faltante
            'vessel_id' => null,     // Faltante
            'company_id' => $this->testCompany->id,
            'departure_port' => 'ARBUE',
            'arrival_port' => null,  // Faltante
            'status' => 'planned',
        ]);

        // Act
        $result = $this->paraguayService->sendImportManifest($invalidVoyage, $this->testUser->id);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('VALIDATION_ERROR', $result['error_code']);
        $this->assertStringContains('Errores de validación', $result['error_message']);
        $this->assertArrayHasKey('details', $result);
        
        // Verificar que contiene errores específicos
        $this->assertContains('El viaje debe tener un número de viaje', $result['details']);
        $this->assertContains('El viaje debe tener una embarcación asignada', $result['details']);
        $this->assertContains('El viaje debe tener puertos de salida y llegada definidos', $result['details']);
    }

    /** @test */
    public function validates_company_has_valid_certificate()
    {
        // Arrange - Mock certificado inválido
        $mockCertificateManager = Mockery::mock(CertificateManagerService::class);
        $mockCertificateManager->shouldReceive('hasValidCertificate')
                              ->with('PY')
                              ->andReturn(false);

        $this->app->instance(CertificateManagerService::class, $mockCertificateManager);
        
        // Recrear servicio con mock actualizado
        $paraguayService = new ParaguayCustomsService($this->testCompany);

        // Act
        $result = $paraguayService->sendImportManifest($this->testVoyage, $this->testUser->id);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('VALIDATION_ERROR', $result['error_code']);
        $this->assertContains('La empresa debe tener certificado digital válido para Paraguay', $result['details']);
    }

    /** @test */
    public function generates_valid_xml_for_paraguay_manifest()
    {
        // Arrange
        $xmlSerializer = new XmlSerializerService($this->testCompany);
        $transactionId = 'MANIFEST_' . $this->testCompany->tax_id . '_' . date('YmdHis');

        // Act
        $xml = $xmlSerializer->createParaguayManifestXml($this->testVoyage, $transactionId);

        // Assert
        $this->assertNotNull($xml);
        $this->assertStringContains('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContains('soap:Envelope', $xml);
        $this->assertStringContains('gdsf:enviarManifiesto', $xml);
        
        // Verificar datos específicos de PARANA.csv
        $this->assertStringContains('MAERSK LINE ARGENTINA S.A.', $xml);
        $this->assertStringContains('V022NB', $xml); // Voyage number
        $this->assertStringContains('PAR13001', $xml); // Vessel name
        $this->assertStringContains('ARBUE', $xml); // Departure port
        $this->assertStringContains('PYTVT', $xml); // Arrival port
        
        // Verificar estructura válida XML
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'XML debe ser válido');
        
        // Verificar elementos específicos Paraguay GDSF
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('gdsf', 'https://secure.aduana.gov.py/gdsf/schema');
        
        $manifestElements = $xpath->query('//gdsf:parametrosManifiesto');
        $this->assertEquals(1, $manifestElements->length, 'Debe tener parámetros del manifiesto');
        
        $companyElements = $xpath->query('//gdsf:empresaTransportista');
        $this->assertEquals(1, $companyElements->length, 'Debe tener datos de empresa');
        
        $voyageElements = $xpath->query('//gdsf:datosViaje');
        $this->assertEquals(1, $voyageElements->length, 'Debe tener datos del viaje');
    }

    /** @test */
    public function generates_transaction_id_with_correct_format()
    {
        // Arrange & Act
        $reflection = new \ReflectionClass($this->paraguayService);
        $method = $reflection->getMethod('generateTransactionId');
        $method->setAccessible(true);
        
        $transactionId = $method->invoke($this->paraguayService, 'MANIFEST');

        // Assert
        $this->assertMatchesRegularExpression(
            '/^MANIFEST_\d+_\d{14}_[a-z0-9]{6}$/',
            $transactionId,
            'Transaction ID debe tener formato correcto'
        );
        
        // Verificar que contiene RUC limpio
        $cleanRuc = preg_replace('/[^0-9]/', '', $this->testCompany->tax_id);
        $this->assertStringContains($cleanRuc, $transactionId);
    }

    /** @test */
    public function handles_xml_generation_errors_gracefully()
    {
        // Arrange - Crear viaje sin datos necesarios para XML
        $emptyVoyage = new Voyage();
        $emptyVoyage->id = 999;

        // Act
        $result = $this->paraguayService->sendImportManifest($emptyVoyage, $this->testUser->id);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('VALIDATION_ERROR', $result['error_code']);
        
        // Verificar que no se creó transacción inválida
        $this->assertDatabaseMissing('webservice_transactions', [
            'voyage_id' => 999,
        ]);
    }

    /** @test */
    public function logs_all_operations_correctly()
    {
        // Arrange
        $mockSoapClient = Mockery::mock(SoapClient::class);
        $mockSoapClient->shouldReceive('__soapCall')
                      ->once()
                      ->andReturn((object) [
                          'xml' => '<?xml version="1.0"?><soap:Envelope><soap:Body><statusCode>OK</statusCode><id>PY001</id></soap:Body></soap:Envelope>'
                      ]);

        $this->app->instance(SoapClient::class, $mockSoapClient);

        // Act
        $result = $this->paraguayService->sendImportManifest($this->testVoyage, $this->testUser->id);

        // Assert - Verificar secuencia de logs esperada
        $expectedLogs = [
            'ParaguayCustomsService inicializado',
            'Iniciando envío Manifiesto Paraguay',
            'Enviando request SOAP Paraguay',
            'Respuesta SOAP Paraguay recibida'
        ];

        foreach ($expectedLogs as $expectedMessage) {
            $this->assertDatabaseHas('webservice_logs', [
                'message' => $expectedMessage,
                'context->service' => 'ParaguayCustomsService',
                'context->company_id' => $this->testCompany->id,
            ]);
        }
    }

    /** @test */
    public function parses_error_responses_correctly()
    {
        // Arrange
        $mockSoapClient = Mockery::mock(SoapClient::class);
        
        $errorResponse = (object) [
            'xml' => '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                    <soap:Body>
                        <RegistrarManifiestoResponse>
                            <statusCode>ERROR</statusCode>
                            <ResponseStatus>RUC de empresa no válido para operaciones</ResponseStatus>
                        </RegistrarManifiestoResponse>
                    </soap:Body>
                </soap:Envelope>'
        ];

        $mockSoapClient->shouldReceive('__soapCall')
                      ->once()
                      ->andReturn($errorResponse);

        $this->app->instance(SoapClient::class, $mockSoapClient);

        // Act
        $result = $this->paraguayService->sendImportManifest($this->testVoyage, $this->testUser->id);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('ERROR', $result['error_code']);
        $this->assertStringContains('RUC de empresa no válido', $result['error_message']);

        // Verificar transacción marcada como error
        $this->assertDatabaseHas('webservice_transactions', [
            'voyage_id' => $this->testVoyage->id,
            'status' => 'error',
            'error_code' => 'ERROR',
        ]);
    }

    /** @test */
    public function can_handle_containers_with_different_types()
    {
        // Arrange - Crear contenedores con tipos específicos de PARANA.csv
        $containerTypes = ['40HC', '20GP', '40GP', '20OT', '40OT'];
        
        foreach ($containerTypes as $type) {
            Container::create([
                'shipment_id' => $this->testVoyage->shipments->first()->id,
                'container_number' => 'TEST' . rand(1000000, 9999999),
                'container_type' => $type,
                'seal_number' => 'GW' . rand(100000, 999999),
                'gross_weight' => rand(8000, 12000),
                'tare_weight' => rand(2000, 4000),
                'is_empty' => false,
                'status' => 'loaded',
            ]);
        }

        // Act - Generar XML con todos los tipos
        $xmlSerializer = new XmlSerializerService($this->testCompany);
        $xml = $xmlSerializer->createParaguayManifestXml($this->testVoyage, 'TEST_CONTAINERS');

        // Assert - Verificar que todos los tipos se mapean correctamente
        $this->assertNotNull($xml);
        
        // Verificar mapeo de códigos Paraguay
        $this->assertStringContains('42G1', $xml); // 40HC/40GP
        $this->assertStringContains('22G1', $xml); // 20GP
        $this->assertStringContains('22U1', $xml); // 20OT
        $this->assertStringContains('42U1', $xml); // 40OT
    }

    /** @test */
    public function respects_retry_configuration_for_soap_faults()
    {
        // Arrange
        $retryableFault = new SoapFault('Server.Timeout', 'Connection timeout');
        $nonRetryableFault = new SoapFault('Client.AuthenticationFailed', 'Invalid credentials');

        // Test retryable fault
        $mockSoapClient = Mockery::mock(SoapClient::class);
        $mockSoapClient->shouldReceive('__soapCall')->andThrow($retryableFault);
        $this->app->instance(SoapClient::class, $mockSoapClient);

        $result = $this->paraguayService->sendImportManifest($this->testVoyage, $this->testUser->id);
        $this->assertTrue($result['can_retry']);

        // Test non-retryable fault
        $mockSoapClient2 = Mockery::mock(SoapClient::class);
        $mockSoapClient2->shouldReceive('__soapCall')->andThrow($nonRetryableFault);
        $this->app->instance(SoapClient::class, $mockSoapClient2);

        $result2 = $this->paraguayService->sendImportManifest($this->testVoyage, $this->testUser->id);
        $this->assertFalse($result2['can_retry']);
    }

    /** @test */
    public function integration_test_full_workflow()
    {
        /**
         * Test de integración completo simulando workflow real:
         * 1. Envío de manifiesto
         * 2. Consulta de estado
         * 3. Rectificación si es necesario
         */
        
        // Step 1: Envío exitoso
        $mockSoapClient = Mockery::mock(SoapClient::class);
        $mockSoapClient->shouldReceive('__soapCall')
                      ->with('enviarManifiesto', Mockery::any(), null, Mockery::any())
                      ->once()
                      ->andReturn((object) ['xml' => '<?xml version="1.0"?><soap:Envelope><soap:Body><statusCode>OK</statusCode><id>PY2025INT001</id><MessageHeaderDocument><ram:ID>15000TEMF000001C</ram:ID></MessageHeaderDocument></soap:Body></soap:Envelope>']);

        $this->app->instance(SoapClient::class, $mockSoapClient);
        
        $manifestResult = $this->paraguayService->sendImportManifest($this->testVoyage, $this->testUser->id);
        $this->assertTrue($manifestResult['success']);
        $paraguayRef = $manifestResult['paraguay_reference'];

        // Step 2: Consulta de estado
        $mockSoapClient->shouldReceive('__soapCall')
                      ->with('consultarEstado', Mockery::any(), null, Mockery::any())
                      ->once()
                      ->andReturn((object) ['xml' => '<?xml version="1.0"?><soap:Envelope><soap:Body><statusCode>PROCESADO</statusCode><detalles>Manifiesto procesado correctamente</detalles></soap:Body></soap:Envelope>']);

        $statusResult = $this->paraguayService->queryManifestStatus($paraguayRef);
        $this->assertTrue($statusResult['success']);
        $this->assertEquals('PROCESADO', $statusResult['status']);

        // Step 3: Rectificación
        $mockSoapClient->shouldReceive('__soapCall')
                      ->with('rectificarManifiesto', Mockery::any(), null, Mockery::any())
                      ->once()
                      ->andReturn((object) ['xml' => '<?xml version="1.0"?><soap:Envelope><soap:Body><statusCode>OK</statusCode><id>PY2025INT001R001</id></soap:Body></soap:Envelope>']);

        $rectifyResult = $this->paraguayService->rectifyManifest($paraguayRef, ['gross_weight' => '26000.00']);
        $this->assertTrue($rectifyResult['success']);

        // Verificar que el workflow completo se registró correctamente
        $this->assertDatabaseHas('webservice_transactions', ['webservice_type' => 'manifiesto', 'status' => 'success']);
        $this->assertDatabaseHas('webservice_responses', ['paraguay_gdsf_reference' => 'PY2025INT001']);
        
        // Verificar logs del workflow completo
        $logCount = DB::table('webservice_logs')
                     ->where('context->service', 'ParaguayCustomsService')
                     ->count();
        $this->assertGreaterThan(5, $logCount, 'Debe haber múltiples logs del workflow');
    }
}