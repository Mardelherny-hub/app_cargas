<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\PackagingType;
use App\Models\ShipmentItem;
use App\Services\Simple\HidroviaTransshipmentContainerBultoWriter;
use InvalidArgumentException;
use Tests\TestCase;
use XMLWriter;

/**
 * Contrato local de Bulto para contenedores de transbordo Hidrovía.
 * No necesita BD, WSAA ni conexión SOAP. La validez del sobre SOAP completo
 * y de cantBultosTotFrac sigue pendiente de validación documental/aduanera.
 */
class HidroviaTransshipmentContainerBultoWriterTest extends TestCase
{
    private function fixtures(array $billOverrides = [], array $itemOverrides = [], array $containerOverrides = []): array
    {
        $bill = (new BillOfLading())->forceFill(array_merge([
            'id' => 101, 'is_transit_transshipment' => 'S', 'is_fractional' => false,
        ], $billOverrides));
        $item = (new ShipmentItem())->forceFill(array_merge([
            'id' => 201, 'packaging_code' => 'CN', 'item_description' => 'CARGA QA',
            'cargo_marks' => 'MARCA QUE NO DEBE ENVIARSE', 'package_quantity' => 12,
        ], $itemOverrides));
        $item->setRelation('packagingType', null);
        $container = (new Container())->forceFill(array_merge([
            'id' => 301, 'container_number' => 'QA-CONT-0001', 'condition' => 'L',
        ], $containerOverrides));
        return [$bill, $item, $container];
    }

    private function write(BillOfLading $bill, ShipmentItem $item, Container $container, string $weight = '125.50'): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        (new HidroviaTransshipmentContainerBultoWriter())->write($writer, $bill, $item, $container, $weight);
        return $writer->outputMemory();
    }

    public function test_container_transshipment_excludes_inner_package_counts_and_marks(): void
    {
        [$bill, $item, $container] = $this->fixtures();
        $before = [$bill->getAttributes(), $item->getAttributes(), $container->getAttributes()];
        $xml = $this->write($bill, $item, $container);
        $this->assertStringContainsString('<pesoBruto>125.5000</pesoBruto>', $xml);
        $this->assertStringContainsString('<pesoBrutoTotFrac>125.5000</pesoBrutoTotFrac>', $xml);
        $this->assertStringContainsString('<codTipEmbalaje>CN</codTipEmbalaje>', $xml);
        $this->assertStringContainsString('<indCargSuelt>N</indCargSuelt>', $xml);
        $this->assertStringContainsString('<idContenedor>QA-CONT-0001</idContenedor>', $xml);
        $this->assertStringNotContainsString('cantBultos', $xml);
        $this->assertStringNotContainsString('marcaNro', $xml);
        $this->assertStringNotContainsString('MARCA QUE NO DEBE ENVIARSE', $xml);
        $this->assertSame($before, [$bill->getAttributes(), $item->getAttributes(), $container->getAttributes()]);
    }

    public function test_weight_is_preserved_with_four_decimals_without_float_rounding(): void
    {
        [$bill, $item, $container] = $this->fixtures();
        $this->assertStringContainsString('<pesoBruto>0.0001</pesoBruto>', $this->write($bill, $item, $container, '0.0001'));
        $this->assertStringContainsString('<pesoBruto>9999999999.9999</pesoBruto>', $this->write($bill, $item, $container, '9999999999.9999'));
        $this->assertStringContainsString('<pesoBruto>0.0000</pesoBruto>', $this->write($bill, $item, $container, '0'));
    }

    public function test_real_existing_container_catalog_code_t_maps_to_cn(): void
    {
        [$bill, $item, $container] = $this->fixtures([], ['packaging_code' => '']);
        $type = (new PackagingType())->forceFill([
            'code' => 'T', 'name' => 'CONTENEDOR', 'argentina_ws_code' => 'T', 'unece_code' => null,
        ]);
        $item->setRelation('packagingType', $type);
        $this->assertStringContainsString('<codTipEmbalaje>CN</codTipEmbalaje>', $this->write($bill, $item, $container));
    }

    public function test_invalid_codes_are_never_assumed_to_mean_container(): void
    {
        [$bill, $item, $container] = $this->fixtures([], ['packaging_code' => 'BX']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('código de embalaje CN');
        $this->write($bill, $item, $container);
    }

    public function test_non_transshipment_is_rejected(): void
    {
        [$bill, $item, $container] = $this->fixtures(['is_transit_transshipment' => 'N']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no está marcado');
        $this->write($bill, $item, $container);
    }

    public function test_fractional_shipment_is_blocked_until_totals_are_defined(): void
    {
        [$bill, $item, $container] = $this->fixtures(['is_fractional' => true]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fraccionados');
        $this->write($bill, $item, $container);
    }

    public function test_empty_container_is_not_written_as_loaded_cargo(): void
    {
        [$bill, $item, $container] = $this->fixtures([], [], ['condition' => 'V']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contenedor vacío');
        $this->write($bill, $item, $container);
    }

    public function test_invalid_or_oversized_container_number_is_rejected(): void
    {
        [$bill, $item, $container] = $this->fixtures([], [], ['container_number' => str_repeat('C', 17)]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('máximo 16 caracteres');
        $this->write($bill, $item, $container);
    }

    public function test_invalid_decimal_precision_is_rejected_without_truncation(): void
    {
        [$bill, $item, $container] = $this->fixtures();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('máximo cuatro decimales');
        $this->write($bill, $item, $container, '10.00001');
    }

    public function test_negative_weight_is_rejected(): void
    {
        [$bill, $item, $container] = $this->fixtures();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Peso de transbordo inválido');
        $this->write($bill, $item, $container, '-1.00');
    }

    public function test_description_is_escaped_and_not_truncated(): void
    {
        [$bill, $item, $container] = $this->fixtures([], ['item_description' => 'QA & <PRECINTOS>']);
        $xml = $this->write($bill, $item, $container);
        $this->assertStringContainsString('<descMercaderia>QA &amp; &lt;PRECINTOS&gt;</descMercaderia>', $xml);
    }

    public function test_description_longer_than_500_is_rejected(): void
    {
        [$bill, $item, $container] = $this->fixtures([], ['item_description' => str_repeat('X', 501)]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supera 500');
        $this->write($bill, $item, $container);
    }
}
