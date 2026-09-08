<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Company;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Services\Simple\ArgentinaDeconsolidatedService;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;
use XMLWriter;

class ArgentinaDeconsolidatedServiceContractTest extends TestCase
{
    #[Test]
    public function service_uses_the_real_singular_database_type_and_official_actions(): void
    {
        $config = $this->serviceConfig('testing');

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
        $config = $this->serviceConfig('production');

        $this->assertSame(
            'https://webservicesadu.afip.gob.ar/DIAV2/wgesinformacionanticipada/wgesinformacionanticipada.asmx',
            $config['webservice_url']
        );
    }

    #[Test]
    public function transaction_id_matches_the_afip_twenty_character_contract(): void
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

        $this->assertSame(20, strlen($id));
        $this->assertMatchesRegularExpression('/^DEC[0-9]{12}[A-Z0-9]{5}$/', $id);
    }

    #[Test]
    public function bill_id_normalization_is_deterministic_and_does_not_invent_ids(): void
    {
        $reflection = new ReflectionClass(ArgentinaDeconsolidatedService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('normalizeBillIds');
        $method->setAccessible(true);

        $ids = $method->invoke($service, [5, '2', 5, 0, -3, '9']);

        $this->assertSame([5, 2, 9], $ids);
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

    #[Test]
    public function merchandise_weight_keeps_real_decimal_precision(): void
    {
        $this->assertSame('28163.07', $this->generatorPrivate('decimalString', [28163.07]));
        $this->assertSame('24523.03', $this->generatorPrivate('decimalString', ['24523.030']));
        $this->assertSame('24210', $this->generatorPrivate('decimalString', ['24210.000']));
    }

    #[Test]
    public function afip_container_condition_accepts_only_house_or_pier_semantics(): void
    {
        $this->assertSame('H', $this->generatorPrivate('containerCondition', ['h', 'condición']));
        $this->assertSame('P', $this->generatorPrivate('containerCondition', ['P', 'condición']));

        $this->expectException(Exception::class);
        $this->generatorPrivate('containerCondition', ['V', 'condición']);
    }

    #[Test]
    public function csc_expiry_satisfies_the_container_document_requirement(): void
    {
        $container = $this->container();
        $container->csc_expiry_date = '2030-01-01';

        $bill = $this->billWithContainer($container, ['H']);

        $this->generatorPrivate('validateContainer', [$container, $bill]);
        $this->assertTrue(true);
    }

    #[Test]
    public function acep_satisfies_the_container_document_requirement_without_csc_expiry(): void
    {
        $container = $this->container();
        $container->csc_expiry_date = null;
        $container->acep = 'ACEP2030ABC123';

        $bill = $this->billWithContainer($container, ['P']);

        $this->generatorPrivate('validateContainer', [$container, $bill]);
        $this->assertTrue(true);
    }

    #[Test]
    public function generic_expiry_date_is_not_used_as_an_acep_or_csc_expiry_substitute(): void
    {
        $container = $this->container();
        $container->expiry_date = '2030-01-01';
        $container->csc_expiry_date = null;
        $container->acep = null;

        $bill = $this->billWithContainer($container, ['P']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('debe informar FechaVencimientoContenedor o ACEP');
        $this->generatorPrivate('validateContainer', [$container, $bill]);
    }

    #[Test]
    public function container_condition_for_desc_comes_from_bill_items_not_global_container_default(): void
    {
        $container = $this->container();
        $container->container_condition = 'P';
        $container->acep = 'ABC123';

        $bill = $this->billWithContainer($container, ['H']);

        $condition = $this->generatorPrivate('containerConditionForBill', [$container, $bill]);

        $this->assertSame('H', $condition);
    }

    #[Test]
    public function conflicting_item_conditions_for_the_same_container_are_rejected(): void
    {
        $container = $this->container();
        $container->acep = 'ABC123';
        $bill = $this->billWithContainer($container, ['H', 'P']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('condiciones H/P distintas');
        $this->generatorPrivate('containerConditionForBill', [$container, $bill]);
    }

    #[Test]
    public function operational_default_weights_are_preserved_in_model_but_not_serialized_by_desc(): void
    {
        $container = $this->container();
        $container->container_condition = 'P';
        $container->tare_weight_kg = 2200;
        $container->current_gross_weight_kg = 30000;
        $container->acep = 'ACEP123';

        $bill = $this->billWithContainer($container, ['H']);
        $xml = $this->writeContainersXml($bill);

        $this->assertStringContainsString('<IdentificadorContenedor>MSCU1234567</IdentificadorContenedor>', $xml);
        $this->assertStringContainsString('<CondicionContenedor>H</CondicionContenedor>', $xml);
        $this->assertStringContainsString('<Acep>ACEP123</Acep>', $xml);
        $this->assertStringNotContainsString('<Tara>', $xml);
        $this->assertStringNotContainsString('<PesoBruto>', $xml);
        $this->assertSame(2200, $container->tare_weight_kg);
        $this->assertSame(30000, $container->current_gross_weight_kg);
    }

    #[Test]
    public function line_integer_fields_are_not_silently_rounded(): void
    {
        $this->assertSame('3700', $this->generatorPrivate('integerString', [3700, 'cantidad', 10]));

        $this->expectException(Exception::class);
        $this->generatorPrivate('integerString', [3700.5, 'cantidad', 10]);
    }

    private function serviceConfig(string $environment): array
    {
        $company = new Company();
        $company->id = 10;
        $company->ws_environment = $environment;

        $user = new User();
        $user->id = 20;

        $service = new ArgentinaDeconsolidatedService($company, $user);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getWebserviceConfig');
        $method->setAccessible(true);

        return $method->invoke($service);
    }

    private function parse(string $xml, string $method): array
    {
        $reflection = new ReflectionClass(ArgentinaDeconsolidatedService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $parser = $reflection->getMethod('parseAfipResponse');
        $parser->setAccessible(true);

        return $parser->invoke($service, $xml, $method);
    }

    private function generatorPrivate(string $methodName, array $arguments)
    {
        $reflection = new ReflectionClass(SimpleXmlGeneratorDesconsolidado::class);
        $generator = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($generator, $arguments);
    }

    private function container(): Container
    {
        $container = new Container();
        $container->id = 7;
        $container->container_number = 'MSCU1234567';
        $container->operator_client_id = null;
        $container->argentina_container_code = null;
        $container->shipper_seal = null;
        $container->tare_weight_kg = null;
        $container->current_gross_weight_kg = null;
        $container->setRelation('containerType', null);

        return $container;
    }

    private function billWithContainer(Container $container, array $conditions): BillOfLading
    {
        $bill = new BillOfLading();
        $bill->id = 9;
        $bill->discharge_customs_code = null;
        $bill->operational_discharge_code = null;

        $items = collect();
        foreach ($conditions as $index => $condition) {
            $item = new ShipmentItem();
            $item->id = 100 + $index;
            $item->container_condition = $condition;
            $item->setRelation('containers', collect([$container]));
            $items->push($item);
        }

        $bill->setRelation('shipmentItems', $items);

        return $bill;
    }

    private function writeContainersXml(BillOfLading $bill): string
    {
        $reflection = new ReflectionClass(SimpleXmlGeneratorDesconsolidado::class);
        $generator = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('writeContainers');
        $method->setAccessible(true);

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $method->invoke($generator, $writer, $bill);
        $writer->endDocument();

        return $writer->outputMemory();
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
