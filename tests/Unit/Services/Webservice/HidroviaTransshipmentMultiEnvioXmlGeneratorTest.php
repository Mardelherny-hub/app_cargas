<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Services\Simple\HidroviaTransshipmentContainerBultoWriter;
use App\Services\Simple\HidroviaTransshipmentMultiEnvioXmlGenerator;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Sólo QA local sobre objetos ficticios; no usa BD, WSAA ni SOAP.
 * Prueba el fragmento <envios>, NO declara válido un RegistrarTitEnvios completo.
 */
class HidroviaTransshipmentMultiEnvioXmlGeneratorTest extends TestCase
{
    private function item(int $id, int $number, int $packages, string $weight, array $containers = []): ShipmentItem
    {
        $item = (new ShipmentItem())->forceFill([
            'id' => $id,
            'line_number' => $number,
            'package_quantity' => $packages,
            'gross_weight_kg' => $weight,
            'item_description' => 'MERCADERIA QA',
            'cargo_marks' => 'MARK-' . $number,
            'packaging_code' => $containers ? 'CN' : 'BX',
        ]);
        $item->setRelation('packagingType', null);
        $item->setRelation('containers', collect($containers));
        return $item;
    }

    private function container(int $id): Container
    {
        return (new Container())->forceFill([
            'id' => $id, 'container_number' => 'QA-CONT-' . $id, 'condition' => 'L',
        ]);
    }

    /** @param array<int,ShipmentItem> $items */
    private function bill(array $items, array $attributes = []): BillOfLading
    {
        $bill = (new BillOfLading())->forceFill(array_merge([
            'id' => 31,
            'bill_number' => 'QA-BL-31',
            'is_transit_transshipment' => 'S',
            'is_fractional' => false,
            'id_decla' => 'QA-ID-DECL',
        ], $attributes));
        $bill->setRelation('shipmentItems', collect($items));
        return $bill;
    }

    private function row(int $itemId, int $quantity, string $weight, ?int $containerId = null): array
    {
        return [
            'shipment_item_id' => $itemId,
            'container_id' => $containerId,
            'package_quantity' => $quantity,
            'gross_weight_kg' => $weight,
        ];
    }

    /** @param array<int,array<string,mixed>> $first @param array<int,array<string,mixed>> $second */
    private function plan(array $first, array $second): array
    {
        return [
            'voyage_id' => 7,
            'bill_of_lading_id' => 31,
            'shipments' => [
                ['id_envio' => 1, 'shipment_id' => 11, 'vessel_id' => 21, 'lines' => $first],
                ['id_envio' => 2, 'shipment_id' => 12, 'vessel_id' => 22, 'lines' => $second],
            ],
        ];
    }

    private function render(BillOfLading $bill, array $plan): DOMXPath
    {
        $generator = new HidroviaTransshipmentMultiEnvioXmlGenerator(
            new HidroviaTransshipmentContainerBultoWriter()
        );
        $xml = $generator->createEnviosFragment($bill, $plan);
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml));
        $this->assertSame('envios', $document->documentElement->nodeName);
        return new DOMXPath($document);
    }

    private function rejected(BillOfLading $bill, array $plan, string $reason): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($reason);
        $this->render($bill, $plan);
    }

    public function test_one_title_has_two_separate_barge_shipments_with_exact_fractional_weights(): void
    {
        $bill = $this->bill([$this->item(41, 2, 10, '100.25')]);
        $before = $bill->shipmentItems->first()->getAttributes();
        $xpath = $this->render($bill, $this->plan(
            [$this->row(41, 4, '40.10')],
            [$this->row(41, 6, '60.15')],
        ));
        $this->assertSame(2, $xpath->query('/envios/Envio')->length);
        $this->assertSame(2, $xpath->query('/envios/Envio/destinaciones/Destinacion')->length);
        $this->assertSame('1', $xpath->evaluate('string(/envios/Envio[1]/idEnvio)'));
        $this->assertSame('2', $xpath->evaluate('string(/envios/Envio[2]/idEnvio)'));
        $this->assertSame('QA-ID-DECL', $xpath->evaluate('string(/envios/Envio[2]/destinaciones/Destinacion/idDecla)'));
        $this->assertSame('40.1000', $xpath->evaluate('string(/envios/Envio[1]/destinaciones/Destinacion/items/Item/peso)'));
        $this->assertSame('60.1500', $xpath->evaluate('string(/envios/Envio[2]/destinaciones/Destinacion/items/Item/peso)'));
        $this->assertSame('4', $xpath->evaluate('string(/envios/Envio[1]/destinaciones/Destinacion/bultos/Bulto/cantBultos)'));
        $this->assertSame('6', $xpath->evaluate('string(/envios/Envio[2]/destinaciones/Destinacion/bultos/Bulto/cantBultos)'));
        $this->assertSame($before, $bill->shipmentItems->first()->getAttributes());
    }

    public function test_real_containers_are_emitted_once_and_inner_package_counts_and_marks_are_omitted(): void
    {
        $bill = $this->bill([$this->item(41, 1, 10, '100.25', [
            $this->container(51), $this->container(52),
        ])]);
        $xpath = $this->render($bill, $this->plan(
            [$this->row(41, 4, '40.10', 51)],
            [$this->row(41, 6, '60.15', 52)],
        ));
        $this->assertSame(2, $xpath->query('//Bulto')->length);
        $this->assertSame('QA-CONT-51', $xpath->evaluate('string(/envios/Envio[1]//Bulto/idContenedor)'));
        $this->assertSame('QA-CONT-52', $xpath->evaluate('string(/envios/Envio[2]//Bulto/idContenedor)'));
        $this->assertSame(0, $xpath->query('//Bulto/cantBultos | //Bulto/cantBultosTotFrac | //Bulto/marcaNro')->length);
        $this->assertSame('N', $xpath->evaluate('string(/envios/Envio[1]//Bulto/indCargSuelt)'));
    }

    public function test_same_item_split_across_two_containers_in_one_barge_is_only_one_item_with_summed_weight(): void
    {
        $bill = $this->bill([$this->item(41, 3, 10, '100.25', [
            $this->container(51), $this->container(52), $this->container(53),
        ])]);
        $xpath = $this->render($bill, $this->plan(
            [$this->row(41, 2, '20.10', 51), $this->row(41, 3, '30.05', 52)],
            [$this->row(41, 5, '50.10', 53)],
        ));
        $this->assertSame(1, $xpath->query('/envios/Envio[1]//items/Item')->length);
        $this->assertSame('50.1500', $xpath->evaluate('string(/envios/Envio[1]//items/Item/peso)'));
        $this->assertSame(2, $xpath->query('/envios/Envio[1]//bultos/Bulto')->length);
    }

    public function test_two_different_items_keep_their_own_quantities_and_weights_per_barge(): void
    {
        $bill = $this->bill([
            $this->item(41, 1, 10, '100.25'),
            $this->item(42, 2, 2, '20.50'),
        ]);
        $xpath = $this->render($bill, $this->plan(
            [$this->row(41, 4, '40.10'), $this->row(42, 2, '20.50')],
            [$this->row(41, 6, '60.15')],
        ));
        $this->assertSame(2, $xpath->query('/envios/Envio[1]//items/Item')->length);
        $this->assertSame('20.5000', $xpath->evaluate('string(/envios/Envio[1]//items/Item[nroItem="2"]/peso)'));
        $this->assertSame(1, $xpath->query('/envios/Envio[2]//items/Item')->length);
    }

    public function test_missing_weight_is_rejected_before_serialization(): void
    {
        $this->rejected($this->bill([$this->item(41, 1, 10, '100.25')]),
            $this->plan([$this->row(41, 4, '40.10')], [$this->row(41, 6, '60.14')]), 'no completan exactamente');
    }

    public function test_duplicated_shipment_is_rejected(): void
    {
        $plan = $this->plan([$this->row(41, 4, '40.10')], [$this->row(41, 6, '60.15')]);
        $plan['shipments'][1]['shipment_id'] = 11;
        $this->rejected($this->bill([$this->item(41, 1, 10, '100.25')]), $plan, 'no puede aparecer dos veces');
    }

    public function test_broken_envio_sequence_is_rejected(): void
    {
        $plan = $this->plan([$this->row(41, 4, '40.10')], [$this->row(41, 6, '60.15')]);
        $plan['shipments'][1]['id_envio'] = 3;
        $this->rejected($this->bill([$this->item(41, 1, 10, '100.25')]), $plan, 'secuencia');
    }

    public function test_reusing_a_container_in_two_barges_is_rejected(): void
    {
        $bill = $this->bill([$this->item(41, 1, 10, '100.25', [$this->container(51), $this->container(52)])]);
        $this->rejected($bill, $this->plan(
            [$this->row(41, 4, '40.10', 51)], [$this->row(41, 6, '60.15', 51)]
        ), 'varias veces');
    }

    public function test_foreign_title_is_rejected(): void
    {
        $plan = $this->plan([$this->row(41, 4, '40.10')], [$this->row(41, 6, '60.15')]);
        $plan['bill_of_lading_id'] = 32;
        $this->rejected($this->bill([$this->item(41, 1, 10, '100.25')]), $plan, 'no corresponde');
    }

    public function test_fractional_title_is_not_silently_supported(): void
    {
        $this->rejected($this->bill([$this->item(41, 1, 10, '100.25')], ['is_fractional' => true]),
            $this->plan([$this->row(41, 4, '40.10')], [$this->row(41, 6, '60.15')]), 'no fraccionado');
    }

    public function test_invalid_declaration_never_becomes_fake_reference(): void
    {
        $this->rejected($this->bill([$this->item(41, 1, 10, '100.25')], [
            'id_decla' => '', 'permiso_embarque' => 'VACIO',
        ]), $this->plan([$this->row(41, 4, '40.10')], [$this->row(41, 6, '60.15')]), 'idDecla real');
    }

    public function test_missing_physical_container_must_be_rejected_even_if_all_item_totals_match(): void
    {
        // BL con dos contenedores en el primer ítem. Se asignan todos sus
        // bultos/peso a UNO solo; la segunda barcaza recibe otro ítem real.
        // No hay fila nula ni sumas incorrectas: sólo falta el contenedor 52.
        $bill = $this->bill([
            $this->item(41, 1, 10, '100.25', [$this->container(51), $this->container(52)]),
            $this->item(42, 2, 2, '20.50'),
        ]);
        $this->rejected($bill, $this->plan(
            [$this->row(41, 10, '100.25', 51)],
            [$this->row(42, 2, '20.50')],
        ), 'todos los contenedores reales');
    }

    public function test_a_container_cannot_be_missing_even_if_totals_match(): void
    {
        // Ningún envío puede apropiarse del peso/bultos de otro contenedor real.
        $bill = $this->bill([$this->item(41, 1, 10, '100.25', [$this->container(51), $this->container(52)])]);
        $this->rejected($bill, $this->plan(
            [$this->row(41, 4, '40.10', 51)],
            [$this->row(41, 6, '60.15', null)],
        ), 'contenedor real');
    }
}
