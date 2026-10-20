<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Services\Simple\HidroviaTransshipmentContainerBultoWriter;
use App\Services\Simple\HidroviaTransshipmentMultiEnvioXmlGenerator;
use App\Services\Simple\HidroviaTransshipmentRegistrarTitEnviosXmlAssembler;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Sólo QA local del ensamblado de RegistrarTitEnvios, sin BD, WSAA ni SOAP real.
 * La plantilla es sintética, con los nombres/orden del generador existente.
 * NO prueba conformidad XSD ni autorización de transmisión por ARCA.
 */
class HidroviaTransshipmentRegistrarTitEnviosXmlAssemblerTest extends TestCase
{
    private const NS = 'Ar.Gob.Afip.Dga.wgesregsintia2';

    private function bill(array $attributes = [], bool $containerized = false): BillOfLading
    {
        $bill = (new BillOfLading())->forceFill(array_replace([
            'id' => 31, 'bill_number' => 'QA-BL-31', 'id_decla' => 'QA-DECL',
            'is_transit_transshipment' => 'S', 'is_fractional' => false,
        ], $attributes));
        $item = (new ShipmentItem())->forceFill([
            'id' => 41, 'line_number' => 2, 'package_quantity' => 10,
            'gross_weight_kg' => '100.25', 'packaging_code' => $containerized ? 'CN' : 'BX',
            'item_description' => 'CARGA QA', 'cargo_marks' => 'MARCA QA',
        ]);
        $containers = $containerized ? [
            (new Container())->forceFill(['id' => 51, 'container_number' => 'QA-CONT-51', 'condition' => 'L']),
            (new Container())->forceFill(['id' => 52, 'container_number' => 'QA-CONT-52', 'condition' => 'L']),
        ] : [];
        $item->setRelation('containers', collect($containers));
        $item->setRelation('packagingType', null);
        $bill->setRelation('shipmentItems', collect([$item]));
        return $bill;
    }

    private function plan(bool $containerized = false): array
    {
        return [
            'voyage_id' => 7, 'bill_of_lading_id' => 31,
            'shipments' => [
                ['id_envio' => 1, 'shipment_id' => 11, 'vessel_id' => 21, 'lines' => [[
                    'shipment_item_id' => 41, 'container_id' => $containerized ? 51 : null,
                    'package_quantity' => 4, 'gross_weight_kg' => '40.10',
                ]]],
                ['id_envio' => 2, 'shipment_id' => 12, 'vessel_id' => 22, 'lines' => [[
                    'shipment_item_id' => 41, 'container_id' => $containerized ? 52 : null,
                    'package_quantity' => 6, 'gross_weight_kg' => '60.15',
                ]]],
            ],
        ];
    }

    private function customs(): array
    {
        // Sintéticos; nunca corresponden a credenciales ni CUIT para envío.
        return [
            11 => [
                'idFiscalATAMIC' => '20123456789',
                'lugOperOrigen' => ['codLugOper' => '12345', 'codCiu' => 'ARSFE'],
                'lugOperDestino' => ['codLugOper' => '1234567', 'codCiu' => 'ARROS'],
            ],
            12 => [
                'idFiscalATAMIC' => '20987654321',
                'lugOperOrigen' => ['codLugOper' => 'A123', 'codCiu' => 'ARPNA'],
                'lugOperDestino' => ['codLugOper' => 'B123', 'codCiu' => 'ARROS'],
            ],
        ];
    }

    private function template(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>
<RegistrarTitEnvios xmlns="Ar.Gob.Afip.Dga.wgesregsintia2">
<argWSAutenticacionEmpresa><Token>QA-TOKEN</Token><Sign>QA-SIGN</Sign><CuitEmpresaConectada>30700000001</CuitEmpresaConectada><TipoAgente>TRSP</TipoAgente><Rol>TRSP</Rol></argWSAutenticacionEmpresa>
<argRegistrarTitEnviosParam><idTransaccion>QA-TX-001</idTransaccion><titulosTransEnvios><TitTransEnvio>
<codViaTrans>8</codViaTrans><idTitTrans>QA-BL-31</idTitTrans><obsDeclaAduInter>OBSERVACION QA</obsDeclaAduInter>
<remitente><nomRazSoc>REM QA</nomRazSoc></remitente><consignatario><nomRazSoc>CON QA</nomRazSoc></consignatario><destinatario><nomRazSoc>DEST QA</nomRazSoc></destinatario>
<indFinCom>S</indFinCom><indFraccTransp>N</indFraccTransp><indConsol>N</indConsol>
<origen><codAdu>001</codAdu></origen><destino><codPais>PY</codPais><codAdu>002</codAdu></destino>
<envios><Envio><destinaciones><Destinacion>
<idDecla>QA-DECL</idDecla><montoFob>0.0000</montoFob><montoFlete>0.0000</montoFlete><montoSeg>0.0000</montoSeg>
<codDivisaFob>USD</codDivisaFob><codDivisaFle>USD</codDivisaFle><codDivisaSeg>USD</codDivisaSeg>
<items><Item><nroItem>2</nroItem><peso>100.2500</peso></Item></items>
<docAnexos><DocAnexo><codTipDoc>380</codTipDoc><documento>INV-QA-1</documento></DocAnexo></docAnexos>
<bultos><Bulto><cantBultos>10</cantBultos><pesoBruto>100.2500</pesoBruto></Bulto></bultos>
</Destinacion></destinaciones><indUltFra>S</indUltFra><idFiscalATAMIC>30700000001</idFiscalATAMIC>
<lugOperOrigen><codLugOper>999</codLugOper><codCiu>ARBUE</codCiu></lugOperOrigen>
<lugOperDestino><codLugOper>888</codLugOper><codCiu>PYTVT</codCiu></lugOperDestino><idEnvio>1</idEnvio></Envio></envios>
</TitTransEnvio></titulosTransEnvios></argRegistrarTitEnviosParam>
</RegistrarTitEnvios></SOAP-ENV:Body></SOAP-ENV:Envelope>
XML;
    }

    private function assemble(
        ?string $template = null,
        ?BillOfLading $bill = null,
        ?array $prepared = null,
        ?array $customs = null
    ): string {
        $assembler = new HidroviaTransshipmentRegistrarTitEnviosXmlAssembler(
            new HidroviaTransshipmentMultiEnvioXmlGenerator(
                new HidroviaTransshipmentContainerBultoWriter()
            )
        );
        return $assembler->assemble(
            $template ?? $this->template(),
            $bill ?? $this->bill(),
            $prepared ?? $this->plan(),
            $customs ?? $this->customs()
        );
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument();
        // El namespace no absoluto es el identificador efectivo del WS actual.
        $previous = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadXML($xml));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('a', self::NS);
        return $xpath;
    }

    private function reject(
        string $message,
        ?string $template = null,
        ?BillOfLading $bill = null,
        ?array $prepared = null,
        ?array $customs = null
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $this->assemble($template, $bill, $prepared, $customs);
    }

    public function test_two_barges_share_one_title_but_get_distinct_weights_and_customs(): void
    {
        $bill = $this->bill();
        $prepared = $this->plan();
        $customs = $this->customs();
        $originalBill = $bill->getAttributes();
        $originalPrepared = $prepared;
        $originalCustoms = $customs;
        $xpath = $this->xpath($this->assemble(null, $bill, $prepared, $customs));

        $this->assertSame(1, $xpath->query('//a:TitTransEnvio')->length);
        $this->assertSame(2, $xpath->query('//a:TitTransEnvio/a:envios/a:Envio')->length);
        $this->assertSame('QA-BL-31', $xpath->evaluate('string(//a:TitTransEnvio/a:idTitTrans)'));
        $this->assertSame(['1', '2'], array_map('strval', array_map(
            fn ($node) => $node->textContent,
            iterator_to_array($xpath->query('//a:Envio/a:idEnvio'))
        )));
        $this->assertSame('40.1000', $xpath->evaluate('string((//a:Envio)[1]//a:Item/a:peso)'));
        $this->assertSame('60.1500', $xpath->evaluate('string((//a:Envio)[2]//a:Item/a:peso)'));
        $this->assertSame('4', $xpath->evaluate('string((//a:Envio)[1]//a:Bulto/a:cantBultos)'));
        $this->assertSame('6', $xpath->evaluate('string((//a:Envio)[2]//a:Bulto/a:cantBultos)'));
        $this->assertSame('20123456789', $xpath->evaluate('string((//a:Envio)[1]/a:idFiscalATAMIC)'));
        $this->assertSame('20987654321', $xpath->evaluate('string((//a:Envio)[2]/a:idFiscalATAMIC)'));
        $this->assertSame('A123', $xpath->evaluate('string((//a:Envio)[2]/a:lugOperOrigen/a:codLugOper)'));
        $this->assertSame('ARPNA', $xpath->evaluate('string((//a:Envio)[2]/a:lugOperOrigen/a:codCiu)'));
        $this->assertSame($originalBill, $bill->getAttributes());
        $this->assertSame($originalPrepared, $prepared);
        $this->assertSame($originalCustoms, $customs);
    }

    public function test_soap_envelope_authentication_title_customs_and_invoice_survive_assembly(): void
    {
        $xml = $this->assemble();
        $xpath = $this->xpath($xml);
        $this->assertSame('QA-TOKEN', $xpath->evaluate('string(//a:argWSAutenticacionEmpresa/a:Token)'));
        $this->assertSame('QA-SIGN', $xpath->evaluate('string(//a:argWSAutenticacionEmpresa/a:Sign)'));
        $this->assertSame('30700000001', $xpath->evaluate('string(//a:argWSAutenticacionEmpresa/a:CuitEmpresaConectada)'));
        $this->assertSame('QA-TX-001', $xpath->evaluate('string(//a:argRegistrarTitEnviosParam/a:idTransaccion)'));
        $this->assertSame('REM QA', $xpath->evaluate('string(//a:TitTransEnvio/a:remitente/a:nomRazSoc)'));
        $this->assertSame('CON QA', $xpath->evaluate('string(//a:TitTransEnvio/a:consignatario/a:nomRazSoc)'));
        $this->assertSame('DEST QA', $xpath->evaluate('string(//a:TitTransEnvio/a:destinatario/a:nomRazSoc)'));
        $this->assertSame('OBSERVACION QA', $xpath->evaluate('string(//a:TitTransEnvio/a:obsDeclaAduInter)'));
        $this->assertSame('PY', $xpath->evaluate('string(//a:TitTransEnvio/a:destino/a:codPais)'));
        $this->assertSame(2, $xpath->query('//a:Envio//a:DocAnexo')->length);
        $this->assertSame(2, $xpath->query('//a:Envio//a:codDivisaFob')->length);
        $this->assertSame(2, $xpath->query('//a:Envio//a:montoFob')->length);
        $this->assertSame(2, $xpath->query('//a:Envio//a:montoFlete')->length);
        $this->assertSame(2, $xpath->query('//a:Envio//a:montoSeg')->length);
        $this->assertSame('INV-QA-1', $xpath->evaluate('string((//a:Envio)[2]//a:DocAnexo/a:documento)'));
        $this->assertSame('0.0000', $xpath->evaluate('string((//a:Envio)[2]//a:montoFob)'));
        $this->assertStringNotContainsString('xmlns=""', $xml);
    }

    public function test_original_one_envio_is_replaced_not_appended(): void
    {
        $xml = $this->assemble();
        $xpath = $this->xpath($xml);
        $this->assertSame(2, $xpath->query('//a:Envio')->length);
        $this->assertSame(0, $xpath->query('//a:Envio/a:idFiscalATAMIC[text()="30700000001"]')->length);
        $this->assertSame(2, $xpath->query('//a:Envio/a:idEnvio')->length);
        $this->assertSame(2, $xpath->query('//a:Envio//a:bultos/a:Bulto')->length);
    }

    public function test_containers_keep_their_distinct_physical_ids_and_transshipment_bulto_shape(): void
    {
        $xpath = $this->xpath($this->assemble(null, $this->bill([], true), $this->plan(true)));
        $this->assertSame('QA-CONT-51', $xpath->evaluate('string((//a:Envio)[1]//a:Bulto/a:idContenedor)'));
        $this->assertSame('QA-CONT-52', $xpath->evaluate('string((//a:Envio)[2]//a:Bulto/a:idContenedor)'));
        $this->assertSame(0, $xpath->query('//a:Bulto/a:cantBultos | //a:Bulto/a:marcaNro')->length);
        $this->assertSame('N', $xpath->evaluate('string((//a:Envio)[2]//a:Bulto/a:indCargSuelt)'));
    }

    public function test_three_barges_keep_exact_package_and_weight_splits(): void
    {
        $plan = $this->plan();
        $plan['shipments'] = [
            ['id_envio' => 1, 'shipment_id' => 11, 'vessel_id' => 21, 'lines' => [[
                'shipment_item_id' => 41, 'container_id' => null, 'package_quantity' => 1, 'gross_weight_kg' => '10.10',
            ]]],
            ['id_envio' => 2, 'shipment_id' => 12, 'vessel_id' => 22, 'lines' => [[
                'shipment_item_id' => 41, 'container_id' => null, 'package_quantity' => 2, 'gross_weight_kg' => '20.10',
            ]]],
            ['id_envio' => 3, 'shipment_id' => 13, 'vessel_id' => 23, 'lines' => [[
                'shipment_item_id' => 41, 'container_id' => null, 'package_quantity' => 7, 'gross_weight_kg' => '70.05',
            ]]],
        ];
        $customs = $this->customs();
        $customs[13] = [
            'idFiscalATAMIC' => '20123456700',
            'lugOperOrigen' => ['codLugOper' => '777', 'codCiu' => 'ARSFE'],
            'lugOperDestino' => ['codLugOper' => '888', 'codCiu' => 'ARROS'],
        ];
        $xpath = $this->xpath($this->assemble(null, null, $plan, $customs));
        $this->assertSame(3, $xpath->query('//a:Envio')->length);
        $this->assertSame('70.0500', $xpath->evaluate('string((//a:Envio)[3]//a:Item/a:peso)'));
        $this->assertSame('7', $xpath->evaluate('string((//a:Envio)[3]//a:Bulto/a:cantBultos)'));
        $this->assertSame('20123456700', $xpath->evaluate('string((//a:Envio)[3]/a:idFiscalATAMIC)'));
    }

    public function test_nonzero_fob_freight_and_insurance_are_each_blocked(): void
    {
        foreach (['montoFob', 'montoFlete', 'montoSeg'] as $field) {
            $template = str_replace("<{$field}>0.0000</{$field}>", "<{$field}>1.0000</{$field}>", $this->template());
            $this->assertNotSame($template, $this->template());
            try {
                $this->assemble($template);
                $this->fail("Importe {$field} no nulo fue aceptado.");
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('reparto de importes comerciales', $error->getMessage());
            }
        }
    }

    public function test_wrong_title_number_is_rejected(): void
    {
        $this->reject('no coincide', str_replace('<idTitTrans>QA-BL-31</idTitTrans>',
            '<idTitTrans>OTRO-BL</idTitTrans>', $this->template()));
    }

    public function test_other_transport_code_is_rejected(): void
    {
        $this->reject('no coincide', str_replace('<codViaTrans>8</codViaTrans>',
            '<codViaTrans>1</codViaTrans>', $this->template()));
    }

    public function test_fractional_title_template_is_rejected(): void
    {
        $this->reject('no coincide', str_replace('<indFraccTransp>N</indFraccTransp>',
            '<indFraccTransp>S</indFraccTransp>', $this->template()));
    }

    public function test_wrong_or_missing_original_declaration_is_rejected(): void
    {
        $this->reject('idDecla difiere', str_replace('<idDecla>QA-DECL</idDecla>',
            '<idDecla>ANOTHER</idDecla>', $this->template()));
    }

    public function test_template_with_two_cargo_titles_is_rejected(): void
    {
        $second = '<TitTransEnvio><codViaTrans>8</codViaTrans><idTitTrans>OTHER</idTitTrans></TitTransEnvio>';
        $this->reject('exactamente un título', str_replace('</titulosTransEnvios>',
            $second . '</titulosTransEnvios>', $this->template()));
    }

    public function test_template_with_two_original_envios_is_rejected(): void
    {
        $this->reject('un solo envío', str_replace('</envios>',
            '<Envio/></envios>', $this->template()));
    }

    public function test_template_with_empty_container_titles_is_rejected(): void
    {
        $this->reject('contenedores vacíos', str_replace('</argRegistrarTitEnviosParam>',
            '<titulosTransContVacios/></argRegistrarTitEnviosParam>', $this->template()));
    }

    public function test_wrong_item_number_is_rejected(): void
    {
        $this->reject('Falta un ítem real', str_replace('<nroItem>2</nroItem>',
            '<nroItem>3</nroItem>', $this->template()));
    }

    public function test_missing_authentication_is_rejected_before_output(): void
    {
        foreach (['Token' => 'QA-TOKEN', 'Sign' => 'QA-SIGN', 'CuitEmpresaConectada' => '30700000001'] as $name => $value) {
            $template = str_replace("<{$name}>{$value}</{$name}>", "<{$name}></{$name}>", $this->template());
            try {
                $this->assemble($template);
                $this->fail("Se aceptó {$name} vacío.");
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString($name, $error->getMessage());
            }
        }
    }

    public function test_invalid_transaction_id_is_rejected(): void
    {
        $this->reject('idTransaccion', str_replace('QA-TX-001', str_repeat('X', 16), $this->template()));
    }

    public function test_unknown_soap_envelope_is_rejected(): void
    {
        $this->reject('no es un sobre SOAP', str_replace('http://schemas.xmlsoap.org/soap/envelope/',
            'urn:not-soap', $this->template()));
    }

    public function test_wrong_arca_namespace_is_rejected(): void
    {
        $this->reject('RegistrarTitEnvios', str_replace('xmlns="' . self::NS . '"',
            'xmlns="urn:other-operation"', $this->template()));
    }

    public function test_missing_customs_by_barge_is_rejected(): void
    {
        $customs = $this->customs();
        unset($customs[12]);
        $this->reject('Faltan datos aduaneros', null, null, null, $customs);
    }

    public function test_incomplete_weight_split_is_rejected(): void
    {
        $plan = $this->plan();
        $plan['shipments'][1]['lines'][0]['gross_weight_kg'] = '60.14';
        $this->reject('no completan exactamente', null, null, $plan);
    }

    public function test_template_with_duplicate_item_numbers_is_rejected(): void
    {
        $duplicate = '<Item><nroItem>2</nroItem><peso>100.2500</peso></Item>';
        $this->reject('duplicados', str_replace('</items>', $duplicate . '</items>', $this->template()));
    }
}
