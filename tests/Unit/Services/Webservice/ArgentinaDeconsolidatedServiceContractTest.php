<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\User;
use App\Services\Simple\ArgentinaDeconsolidatedService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class ArgentinaDeconsolidatedServiceContractTest extends TestCase
{
    #[Test]
    public function service_uses_the_real_singular_database_type_and_official_actions(): void
    {
        $company = new Company();
        $company->id = 10;
        $company->ws_environment = 'testing';

        $user = new User();
        $user->id = 20;

        $service = new ArgentinaDeconsolidatedService($company, $user);
        $reflection = new ReflectionClass($service);

        $method = $reflection->getMethod('getWebserviceConfig');
        $method->setAccessible(true);
        $config = $method->invoke($service);

        $this->assertSame('desconsolidado', $config['webservice_type']);
        $this->assertSame(
            'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            $config['webservice_url']
        );
        $this->assertSame(
            'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RegistrarTitulosDesconsolidador',
            $config['soap_action_registrar']
        );
        $this->assertSame(
            'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/RectificarTitulosDesconsolidador',
            $config['soap_action_rectificar']
        );
        $this->assertSame(
            'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada/EliminarTitulosDesconsolidador',
            $config['soap_action_eliminar']
        );
    }

    #[Test]
    public function production_endpoint_is_the_official_afip_endpoint(): void
    {
        $company = new Company();
        $company->id = 10;
        $company->ws_environment = 'production';

        $user = new User();
        $user->id = 20;

        $service = new ArgentinaDeconsolidatedService($company, $user);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getWebserviceConfig');
        $method->setAccessible(true);
        $config = $method->invoke($service);

        $this->assertSame(
            'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            $config['webservice_url']
        );
    }

    #[Test]
    public function transaction_id_is_exactly_twenty_characters_or_less(): void
    {
        $company = new Company();
        $company->id = 10;
        $company->ws_environment = 'testing';

        $user = new User();
        $user->id = 20;

        $service = new ArgentinaDeconsolidatedService($company, $user);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generateTransactionId');
        $method->setAccessible(true);

        $id = $method->invoke($service);

        $this->assertNotSame('', $id);
        $this->assertLessThanOrEqual(20, strlen($id));
        $this->assertMatchesRegularExpression('/^DEC[0-9]{12}[A-Z0-9]{5}$/', $id);
    }

    #[Test]
    public function afip_identifier_makes_response_success_even_when_warnings_are_present(): void
    {
        $xml = $this->soapResponse(
            'RegistrarTitulosDesconsolidador',
            '<ListaErrores><DetalleError>'
            . '<Codigo>W001</Codigo>'
            . '<Descripcion>Advertencia documental</Descripcion>'
            . '<DescripcionAdicional>Revisar dato no bloqueante</DescripcionAdicional>'
            . '</DetalleError></ListaErrores>'
            . '<IdentificadorViaje>VIAJE123</IdentificadorViaje>'
        );

        $result = $this->parse($xml, 'RegistrarTitulosDesconsolidador');

        $this->assertTrue($result['success']);
        $this->assertSame('VIAJE123', $result['identifier']);
        $this->assertCount(1, $result['details']);
        $this->assertSame('W001', $result['details'][0]['code']);
        $this->assertSame('Advertencia documental', $result['details'][0]['description']);
        $this->assertSame('Revisar dato no bloqueante', $result['details'][0]['additional']);
    }

    #[Test]
    public function all_afip_errors_are_preserved_when_identifier_is_absent(): void
    {
        $xml = $this->soapResponse(
            'RectificarTitulosDesconsolidador',
            '<ListaErrores>'
            . '<DetalleError><Codigo>100</Codigo><Descripcion>Primer error</Descripcion><DescripcionAdicional>A</DescripcionAdicional></DetalleError>'
            . '<DetalleError><Codigo>200</Codigo><Descripcion>Segundo error</Descripcion><DescripcionAdicional>B</DescripcionAdicional></DetalleError>'
            . '</ListaErrores>'
        );

        $result = $this->parse($xml, 'RectificarTitulosDesconsolidador');

        $this->assertFalse($result['success']);
        $this->assertNull($result['identifier']);
        $this->assertCount(2, $result['details']);
        $this->assertStringContainsString('Primer error', $result['error_message']);
        $this->assertStringContainsString('Segundo error', $result['error_message']);
    }

    #[Test]
    public function soap_fault_is_never_reported_as_business_success(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><soap:Fault>'
            . '<faultcode>soap:Server</faultcode>'
            . '<faultstring>Servicio temporalmente no disponible</faultstring>'
            . '</soap:Fault></soap:Body></soap:Envelope>';

        $result = $this->parse($xml, 'EliminarTitulosDesconsolidador');

        $this->assertFalse($result['success']);
        $this->assertSame('Servicio temporalmente no disponible', $result['error_message']);
        $this->assertSame('soap_fault', $result['details'][0]['source']);
    }

    #[Test]
    public function malformed_or_empty_responses_are_errors(): void
    {
        $empty = $this->parse('', 'RegistrarTitulosDesconsolidador');
        $malformed = $this->parse('<not-xml', 'RegistrarTitulosDesconsolidador');

        $this->assertFalse($empty['success']);
        $this->assertFalse($malformed['success']);
    }

    private function parse(string $xml, string $method): array
    {
        $reflection = new ReflectionClass(ArgentinaDeconsolidatedService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $parser = $reflection->getMethod('parseAfipResponse');
        $parser->setAccessible(true);

        return $parser->invoke($service, $xml, $method);
    }

    private function soapResponse(string $method, string $resultBody): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . '<' . $method . 'Response xmlns="Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada">'
            . '<' . $method . 'Result>'
            . $resultBody
            . '</' . $method . 'Result>'
            . '</' . $method . 'Response>'
            . '</soap:Body></soap:Envelope>';
    }
}
