<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Company;
use App\Models\Container;
use App\Models\Port;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArgentinaDeconsolidatedXmlStructureTest extends TestCase
{
    private const TRANSACTION_ID = 'DEC12345678901234567';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function registrar_generates_the_complete_soap_contract_without_runtime_placeholders(): void
    {
        $bill = $this->completeBill();
        $generator = $this->generatorFor($bill);

        $xml = $generator->generateRegistrar(self::TRANSACTION_ID);

        $this->assertStringContainsString(
            '<RegistrarTitulosDesconsolidador xmlns="Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada">',
            $xml
        );
        $this->assertStringContainsString('<argRegistrarTitulosDesconsolidador>', $xml);
        $this->assertAuthentication($xml);
        $this->assertStringContainsString('<IdTransaccion>' . self::TRANSACTION_ID . '</IdTransaccion>', $xml);
        $this->assertStringContainsString('<IdentificadorViaje>VIAJE123</IdentificadorViaje>', $xml);
        $this->assertCompleteTitle($xml);

        $this->assertStringNotContainsString('##', $xml);
        $this->assertStringNotContainsString('PLACEHOLDER', $xml);
        $this->assertStringNotContainsString('<Tara>', $xml);
        $this->assertStringNotContainsString('<PesoBruto>', $xml);
        $this->assertSame(1, $generator->wsaaCalls);
    }

    #[Test]
    public function rectificar_uses_the_same_complete_title_contract_under_the_rectification_method(): void
    {
        $bill = $this->completeBill();
        $generator = $this->generatorFor($bill);

        $xml = $generator->generateRectificar(self::TRANSACTION_ID);

        $this->assertStringContainsString(
            '<RectificarTitulosDesconsolidador xmlns="Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada">',
            $xml
        );
        $this->assertStringContainsString('<argRectificarTitulosDesconsolidador>', $xml);
        $this->assertAuthentication($xml);
        $this->assertCompleteTitle($xml);
        $this->assertSame(1, $generator->wsaaCalls);
    }

    #[Test]
    public function eliminar_generates_only_the_port_and_bill_identification_contract(): void
    {
        $bill = $this->completeBill();
        $generator = $this->generatorFor($bill);

        $xml = $generator->generateEliminar(self::TRANSACTION_ID);

        $this->assertStringContainsString(
            '<EliminarTitulosDesconsolidador xmlns="Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada">',
            $xml
        );
        $this->assertStringContainsString('<argEliminarTitulosDesconsolidador>', $xml);
        $this->assertAuthentication($xml);
        $this->assertStringContainsString('<IdentificadorViaje>VIAJE123</IdentificadorViaje>', $xml);
        $this->assertStringContainsString('<PuertosConocimientos>', $xml);
        $this->assertStringContainsString('<PuertoConocimiento>', $xml);
        $this->assertStringContainsString('<CodigoPuertoEmbarque>ARBUE</CodigoPuertoEmbarque>', $xml);
        $this->assertStringContainsString('<NumeroConocimiento>HOUSE001</NumeroConocimiento>', $xml);

        $this->assertStringNotContainsString('<TitulosDesconsolidador>', $xml);
        $this->assertStringNotContainsString('<TituloDesconsolidador>', $xml);
        $this->assertStringNotContainsString('<Mercaderias>', $xml);
        $this->assertStringNotContainsString('<Contenedores>', $xml);
        $this->assertStringNotContainsString('<IdentificadorTituloMadre>', $xml);
        $this->assertSame(1, $generator->wsaaCalls);
    }

    #[Test]
    public function local_validation_uses_the_same_contract_without_requesting_wsaa_tokens(): void
    {
        $bill = $this->completeBill();
        $bill->shipmentItems->first()->tariff_position = null;
        $generator = $this->generatorFor($bill);

        $result = $generator->validateLocalData('registrar');

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString(
            'PosicionArancelaria',
            implode(' | ', $result['errors'])
        );
        $this->assertSame(0, $generator->wsaaCalls);
    }

    private function assertAuthentication(string $xml): void
    {
        $this->assertStringContainsString('<argWSAutenticacionEmpresa>', $xml);
        $this->assertStringContainsString('<Token>TEST_TOKEN</Token>', $xml);
        $this->assertStringContainsString('<Sign>TEST_SIGN</Sign>', $xml);
        $this->assertStringContainsString('<CuitEmpresaConectada>30712345678</CuitEmpresaConectada>', $xml);
        $this->assertStringContainsString('<TipoAgente>TRSP</TipoAgente>', $xml);
        $this->assertStringContainsString('<Rol>TRSP</Rol>', $xml);
    }

    private function assertCompleteTitle(string $xml): void
    {
        $this->assertStringContainsString('<TituloDesconsolidador>', $xml);
        $this->assertStringContainsString('<FechaEmbarque>2026-09-01T00:00:00</FechaEmbarque>', $xml);
        $this->assertStringContainsString('<CodigoPuertoEmbarque>ARBUE</CodigoPuertoEmbarque>', $xml);
        $this->assertStringContainsString('<NumeroConocimiento>HOUSE001</NumeroConocimiento>', $xml);
        $this->assertStringContainsString('<CodigoPuertoDescarga>PYASU</CodigoPuertoDescarga>', $xml);
        $this->assertStringContainsString('<CodigoPaisDestino>PY</CodigoPaisDestino>', $xml);
        $this->assertStringContainsString('<MarcaBultos>MARCAS HOUSE</MarcaBultos>', $xml);
        $this->assertStringContainsString('<IndicadorConsolidado>S</IndicadorConsolidado>', $xml);
        $this->assertStringContainsString('<IndicadorTransitoTrasbordo>N</IndicadorTransitoTrasbordo>', $xml);
        $this->assertStringContainsString('<PosicionArancelaria>84099990</PosicionArancelaria>', $xml);
        $this->assertStringContainsString('<IndicadorOperadorLogisticoSeguro>N</IndicadorOperadorLogisticoSeguro>', $xml);
        $this->assertStringContainsString('<IndicadorTransitoMonitoreado>N</IndicadorTransitoMonitoreado>', $xml);
        $this->assertStringContainsString('<IndicadorRenar>N</IndicadorRenar>', $xml);
        $this->assertStringContainsString('<CodigoLugarOperativoDescarga>10073</CodigoLugarOperativoDescarga>', $xml);
        $this->assertStringContainsString('<CodigoAduanaDescarga>001</CodigoAduanaDescarga>', $xml);

        $this->assertStringContainsString('<Mercaderias>', $xml);
        $this->assertStringContainsString('<NumeroLinea>1</NumeroLinea>', $xml);
        $this->assertStringContainsString('<CodigoEmbalaje>05</CodigoEmbalaje>', $xml);
        $this->assertStringContainsString('<CondicionContenedor>H</CondicionContenedor>', $xml);
        $this->assertStringContainsString('<CantidadManifestada>10</CantidadManifestada>', $xml);
        $this->assertStringContainsString('<PesoVolumenManifestado>1234.56</PesoVolumenManifestado>', $xml);
        $this->assertStringContainsString('<DescripcionMercaderia>REPUESTOS</DescripcionMercaderia>', $xml);
        $this->assertStringContainsString('<NumeroBultos>BULTOS-1</NumeroBultos>', $xml);

        $this->assertStringContainsString('<Contenedores>', $xml);
        $this->assertStringContainsString('<CaracteristicasContenedor>42G1</CaracteristicasContenedor>', $xml);
        $this->assertStringContainsString('<IdentificadorContenedor>MSCU1234567</IdentificadorContenedor>', $xml);
        $this->assertStringContainsString('<NumeroPrecintoOrigen>PREC123</NumeroPrecintoOrigen>', $xml);
        $this->assertStringContainsString('<Acep>ACEP123</Acep>', $xml);
        $this->assertStringContainsString('<CodigoAduana>001</CodigoAduana>', $xml);
        $this->assertStringContainsString('<IdentificadorTituloMadre>MASTER001</IdentificadorTituloMadre>', $xml);

        $ordered = [
            '<CodigoLugarOperativoDescarga>',
            '<CodigoAduanaDescarga>',
            '<Mercaderias>',
            '<Contenedores>',
            '<IdentificadorTituloMadre>',
        ];
        $positions = array_map(fn (string $needle) => strpos($xml, $needle), $ordered);

        foreach ($positions as $position) {
            $this->assertNotFalse($position);
        }
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    private function generatorFor(BillOfLading $bill): StructuralXmlGenerator
    {
        $company = new Company();
        $company->id = 10;
        $company->tax_id = '30-71234567-8';
        $company->ws_environment = 'testing';

        $relation = Mockery::mock(HasManyThrough::class);
        $relation->shouldReceive('whereNotNull')
            ->with('master_bill_number')
            ->once()
            ->andReturnSelf();
        $relation->shouldReceive('with')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturnSelf();
        $relation->shouldReceive('orderBy')
            ->with('bills_of_lading.id')
            ->once()
            ->andReturnSelf();
        $relation->shouldReceive('get')
            ->once()
            ->andReturn(collect([$bill]));

        $voyage = Mockery::mock(Voyage::class)->makePartial();
        $voyage->id = 33;
        $voyage->company_id = 10;
        $voyage->argentina_voyage_id = 'VIAJE123';
        $voyage->setRelation('company', $company);
        $voyage->shouldReceive('billsOfLading')
            ->once()
            ->andReturn($relation);

        return new StructuralXmlGenerator($voyage, ['environment' => 'testing']);
    }

    private function completeBill(): BillOfLading
    {
        $loadingPort = new Port();
        $loadingPort->code = 'ARBUE';

        $dischargePort = new Port();
        $dischargePort->code = 'PYASU';

        $container = new Container();
        $container->id = 7;
        $container->container_number = 'MSCU1234567';
        $container->operator_client_id = null;
        $container->argentina_container_code = '42G1';
        $container->shipper_seal = 'PREC123';
        $container->csc_expiry_date = null;
        $container->acep = 'ACEP123';
        $container->tare_weight_kg = 2200;
        $container->current_gross_weight_kg = 30000;
        $container->setRelation('containerType', null);

        $item = new ShipmentItem();
        $item->id = 101;
        $item->line_number = 1;
        $item->packaging_code = '05';
        $item->container_condition = 'H';
        $item->package_quantity = 10;
        $item->gross_weight_kg = 1234.56;
        $item->item_description = 'REPUESTOS';
        $item->cargo_marks = 'BULTOS-1';
        $item->tariff_position = '84099990';
        $item->is_secure_logistics_operator = 'N';
        $item->is_monitored_transit = 'N';
        $item->is_renar = 'N';
        $item->foreign_forwarder_name = null;
        $item->foreign_forwarder_tax_id = null;
        $item->foreign_forwarder_country = null;
        $item->consignee_document_type = null;
        $item->consignee_tax_id = null;
        $item->comments = null;
        $item->setRelation('packagingType', null);
        $item->setRelation('cargoType', null);
        $item->setRelation('containers', collect([$container]));

        $bill = new BillOfLading();
        $bill->id = 9;
        $bill->master_bill_number = 'MASTER001';
        $bill->bill_number = 'HOUSE001';
        $bill->loading_date = '2026-09-01';
        $bill->destination_country_code = 'PY';
        $bill->cargo_marks = 'MARCAS HOUSE';
        $bill->is_consolidated = 'S';
        $bill->is_transit_transshipment = 'N';
        $bill->origin_location = null;
        $bill->origin_loading_date = null;
        $bill->origin_country_code = null;
        $bill->notify_party_text = null;
        $bill->operational_discharge_code = '10073';
        $bill->discharge_customs_code = '001';
        $bill->setRelation('loadingPort', $loadingPort);
        $bill->setRelation('dischargePort', $dischargePort);
        $bill->setRelation('transshipmentPort', null);
        $bill->setRelation('consignee', null);
        $bill->setRelation('notifyParty', null);
        $bill->setRelation('shipmentItems', collect([$item]));

        return $bill;
    }
}

class StructuralXmlGenerator extends SimpleXmlGeneratorDesconsolidado
{
    public int $wsaaCalls = 0;

    protected function getWsaaTokens(): array
    {
        $this->wsaaCalls++;

        return [
            'token' => 'TEST_TOKEN',
            'sign' => 'TEST_SIGN',
        ];
    }
}
