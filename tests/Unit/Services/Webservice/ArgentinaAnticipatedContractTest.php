<?php

namespace Tests\Unit\Services\Webservice;

use App\Services\Simple\ArgentinaAnticipatedService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ArgentinaAnticipatedContractTest extends TestCase
{
    private function source(string $path): string
    {
        $content = file_get_contents(base_path($path));
        $this->assertIsString($content);

        return $content;
    }

    private function serviceWithoutConstructor(): ArgentinaAnticipatedService
    {
        return (new ReflectionClass(
            ArgentinaAnticipatedService::class
        ))->newInstanceWithoutConstructor();
    }

    private function invokePrivate(
        object $object,
        string $method,
        array $args = []
    ): mixed {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    public function test_registrar_viaje_uses_official_required_fields_without_fake_fallbacks(): void
    {
        $source = $this->source(
            'app/Services/Simple/SimpleXmlGenerator.php'
        );

        foreach ([
            'IdentificadorMedioTransporte',
            'CodigoPaisProcedencia',
            'CodigoPuertoOrigen',
            'FechaArribo',
            'IndicadorTransporteVacio',
            'IndicadorMercaderiaAbordo',
            'DesignacionTransportista',
            'CodigoPaisTransportista',
            'CodigoNacionalidadMediodeTransporte',
            'CodigoAduana',
        ] as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "Falta el campo oficial {$field}"
            );
        }

        $iaStart = strpos($source, 'public function createRegistrarViajeXml');
        $iaEnd = strpos($source, 'public function createRegistrarConvoyXml');
        $this->assertNotFalse($iaStart);
        $this->assertNotFalse($iaEnd);
        $iaSource = substr($source, $iaStart, $iaEnd - $iaStart);

        foreach ([
            'VACIOS000001',
            'SIN_REGISTRO',
            'SIN_NOMBRE',
            'MERCADERIA GENERAL',
            '__TOKEN__',
            '__SIGN__',
            'take(10)',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $iaSource,
                "No debe existir fallback inventado {$forbidden}"
            );
        }
    }

    public function test_registrar_titulos_uses_official_merchandise_contract(): void
    {
        $source = $this->source(
            'app/Services/Simple/SimpleXmlGenerator.php'
        );

        foreach ([
            'NumeroLinea',
            'CodigoEmbalaje',
            'CantidadManifestada',
            'PesoVolumenManifestado',
            'DescripcionMercaderia',
            'NumeroBultos',
            'PosicionArancelaria',
            'RazonSocialFowarderExterior',
            'CodigoAduanaDescarga',
            'CodigoLugarOperativoDescarga',
        ] as $field) {
            $this->assertStringContainsString(
                "'ar:{$field}'",
                $source,
                "Falta el campo oficial {$field}"
            );
        }
    }

    public function test_anticipada_has_distinct_homologation_and_production_endpoints(): void
    {
        $source = $this->source(
            'app/Services/Simple/ArgentinaAnticipatedService.php'
        );

        $this->assertStringContainsString(
            'https://wsaduhomoext.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            $source
        );
        $this->assertStringContainsString(
            'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            $source
        );
    }

    public function test_wsaa_has_distinct_homologation_and_production_endpoints(): void
    {
        $source = $this->source(
            'app/Services/Simple/SimpleXmlGenerator.php'
        );

        $this->assertStringContainsString(
            'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl',
            $source
        );
        $this->assertStringContainsString(
            'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl',
            $source
        );
    }

    public function test_anticipada_business_send_uses_soap_11(): void
    {
        $service = $this->source(
            'app/Services/Simple/ArgentinaAnticipatedService.php'
        );
        $client = $this->source(
            'app/Services/Webservice/SoapClientService.php'
        );

        $this->assertStringContainsString(
            'SOAP_1_1',
            $service
        );
        $this->assertStringContainsString(
            "\$webserviceType === 'anticipada' ? SOAP_1_1 : SOAP_1_2",
            $client
        );
    }

    public function test_afip_business_error_inside_http_200_is_not_success(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RegistrarViajeResponse>
      <RegistrarViajeResult>
        <ListaErrores>
          <DetalleError>
            <Codigo>1234</Codigo>
            <Descripcion>Dato obligatorio faltante</Descripcion>
            <DescripcionAdicional>CodigoAduana</DescripcionAdicional>
          </DetalleError>
        </ListaErrores>
      </RegistrarViajeResult>
    </RegistrarViajeResponse>
  </soap:Body>
</soap:Envelope>
XML;

        $result = $this->invokePrivate(
            $this->serviceWithoutConstructor(),
            'parseBusinessResponse',
            [$xml, 'RegistrarViaje']
        );

        $this->assertFalse($result['success']);
        $this->assertSame('1234', $result['error_code']);
        $this->assertStringContainsString(
            'Dato obligatorio faltante',
            $result['error_message']
        );
    }

    public function test_registrar_viaje_success_requires_identifier(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RegistrarViajeResponse>
      <RegistrarViajeResult />
    </RegistrarViajeResponse>
  </soap:Body>
</soap:Envelope>
XML;

        $parsed = $this->invokePrivate(
            $this->serviceWithoutConstructor(),
            'parseBusinessResponse',
            [$xml, 'RegistrarViaje']
        );

        $this->assertTrue($parsed['success']);
        $this->assertNull($parsed['external_reference']);

        $source = $this->source(
            'app/Services/Simple/ArgentinaAnticipatedService.php'
        );
        $this->assertStringContainsString(
            'AFIP no devolvió IdentificadorViaje.',
            $source
        );
    }

    public function test_simple_controller_passes_requested_environment_to_service(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Company/Simple/SimpleManifestController.php'
        );

        $this->assertStringContainsString(
            "in_array(\$environment, ['testing', 'production'], true)",
            $source
        );
        $this->assertStringContainsString(
            "['environment' => \$environment]",
            $source
        );
    }

    public function test_no_dead_cerrar_viaje_route_remains(): void
    {
        $routes = $this->source('routes/company.php');

        $this->assertStringNotContainsString(
            "'/anticipada/{voyage}/cerrar-viaje'",
            $routes
        );
        $this->assertStringContainsString(
            "Route::post('/{voyage}/send'",
            $routes
        );
    }

    public function test_container_exposes_operator_client_relation_for_cierre(): void
    {
        $source = $this->source('app/Models/Container.php');

        $this->assertStringContainsString(
            'function operatorClient(): BelongsTo',
            $source
        );
        $this->assertStringContainsString(
            "belongsTo(Client::class, 'operator_client_id')",
            $source
        );
    }
}
