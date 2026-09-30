<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\Simple\ArgentinaAnticipatedService;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class AnticipatedContractComplianceTest extends TestCase
{
    public function test_business_error_response_is_not_treated_as_success(): void
    {
        $service = $this->service();
        $response = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RegistrarViajeResponse xmlns="Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada">
      <RegistrarViajeResult>
        <ListaErrores>
          <DetalleError>
            <Codigo>123</Codigo>
            <Descripcion>Dato inválido</Descripcion>
            <DescripcionAdicional>Campo de prueba</DescripcionAdicional>
          </DetalleError>
        </ListaErrores>
        <IdentificadorViaje>202608000000001A</IdentificadorViaje>
      </RegistrarViajeResult>
    </RegistrarViajeResponse>
  </soap:Body>
</soap:Envelope>
XML;

        $parsed = $this->invokePrivate($service, 'parseAfipBusinessResponse', [
            $response,
            'RegistrarViaje',
        ]);

        $this->assertFalse($parsed['success']);
        $this->assertSame('123', $parsed['error_code']);
        $this->assertStringContainsString('Dato inválido', $parsed['error_message']);
    }

    public function test_success_requires_real_voyage_identifier(): void
    {
        $service = $this->service();
        $response = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RegistrarViajeResponse xmlns="Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada">
      <RegistrarViajeResult>
        <ListaErrores />
        <IdentificadorViaje>202608000000001A</IdentificadorViaje>
      </RegistrarViajeResult>
    </RegistrarViajeResponse>
  </soap:Body>
</soap:Envelope>
XML;

        $parsed = $this->invokePrivate($service, 'parseAfipBusinessResponse', [
            $response,
            'RegistrarViaje',
        ]);

        $this->assertTrue($parsed['success']);
        $this->assertSame('202608000000001A', $parsed['external_reference']);
    }

    public function test_missing_identifier_is_failure_even_with_http_level_success_xml(): void
    {
        $service = $this->service();
        $response = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RegistrarViajeResponse xmlns="Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada">
      <RegistrarViajeResult>
        <ListaErrores />
      </RegistrarViajeResult>
    </RegistrarViajeResponse>
  </soap:Body>
</soap:Envelope>
XML;

        $parsed = $this->invokePrivate($service, 'parseAfipBusinessResponse', [
            $response,
            'RegistrarViaje',
        ]);

        $this->assertFalse($parsed['success']);
        $this->assertSame('MISSING_VOYAGE_ID', $parsed['error_code']);
    }

    public function test_soap_fault_is_failure(): void
    {
        $service = $this->service();
        $response = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <soap:Fault>
      <faultcode>soap:Server</faultcode>
      <faultstring>Rechazado</faultstring>
    </soap:Fault>
  </soap:Body>
</soap:Envelope>
XML;

        $parsed = $this->invokePrivate($service, 'parseAfipBusinessResponse', [
            $response,
            'RegistrarViaje',
        ]);

        $this->assertFalse($parsed['success']);
        $this->assertSame('SOAP_FAULT', $parsed['error_code']);
        $this->assertSame('Rechazado', $parsed['error_message']);
    }

    public function test_environment_selects_official_testing_and_production_hosts(): void
    {
        $service = $this->service();

        $this->invokePrivate($service, 'applyEnvironment', [['environment' => 'testing']]);
        $testing = $this->invokePrivate($service, 'getAnticipatedEndpoint');

        $this->invokePrivate($service, 'applyEnvironment', [['environment' => 'production']]);
        $production = $this->invokePrivate($service, 'getAnticipatedEndpoint');

        $this->assertSame(
            'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            $testing
        );
        $this->assertSame(
            'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            $production
        );
    }

    public function test_source_has_no_fabricated_anticipada_payload_values(): void
    {
        $source = file_get_contents(
            app_path('Services/Simple/SimpleXmlGenerator.php')
        );

        $start = strpos($source, 'public function createRegistrarViajeXml');
        $end = strpos($source, 'private function validateVoyageData', $start);
        $anticipada = substr($source, $start, $end - $start);

        foreach ([
            'VACIOS000001',
            'SIN_REGISTRO',
            "'MERCADERIA GENERAL'",
            "'MERCADERIA'",
            "'N/A'",
            '0000.00.00.000P',
            'take(10)',
            '__TOKEN__',
            '__SIGN__',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $anticipada);
        }
    }

    public function test_service_uses_same_persisted_transaction_id_for_payloads(): void
    {
        $source = file_get_contents(
            app_path('Services/Simple/ArgentinaAnticipatedService.php')
        );

        $this->assertStringNotContainsString(
            "'ANTICIPADA_' . time()",
            $source
        );
        $this->assertStringContainsString(
            '$transaction->transaction_id',
            $source
        );
        $this->assertStringContainsString('SOAP_1_1', $source);
        $this->assertStringNotContainsString('TEMP_REF_', $source);
    }

    private function service(): ArgentinaAnticipatedService
    {
        $company = new Company([
            'legal_name' => 'TEST',
            'tax_id' => '30612732503',
            'country' => 'AR',
        ]);
        $company->id = 1;

        $user = new User();
        $user->id = 1;

        return new ArgentinaAnticipatedService(
            $company,
            $user,
            ['environment' => 'testing']
        );
    }

    private function invokePrivate(object $object, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}
