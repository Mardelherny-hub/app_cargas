<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\Shipment;
use App\Services\Simple\SimpleXmlGenerator;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

/** Prueba local del contrato de dos referencias de trasbordo ya almacenadas en Shipment. */
class HidroviaTransshipmentReferencesTest extends TestCase
{
    private function write(?string $manifest, ?string $document): string
    {
        $company = (new Company())->forceFill(['id' => 1, 'ws_environment' => 'testing']);
        $shipment = (new Shipment())->forceFill([
            'origin_manifest_id' => $manifest,
            'origin_transport_doc' => $document,
        ]);
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startElement('TitTransEnvio');
        (new ReflectionMethod(SimpleXmlGenerator::class, 'writeHidroviaTransshipmentReferences'))
            ->invoke(new SimpleXmlGenerator($company), $writer, $shipment);
        $writer->endElement();
        return $writer->outputMemory();
    }

    #[Test]
    public function recorded_transshipment_references_use_exact_official_names(): void
    {
        $xml = $this->write('MAN-123', 'BL-123');
        $this->assertStringContainsString('<idManiCargaArrPaisPart>MAN-123</idManiCargaArrPaisPart>', $xml);
        $this->assertStringContainsString('<idDocTranspArrPaisPart>BL-123</idDocTranspArrPaisPart>', $xml);
        $this->assertLessThan(strpos($xml, '<idDocTranspArrPaisPart>'), strpos($xml, '<idManiCargaArrPaisPart>'));
    }

    #[Test]
    public function empty_optional_references_are_not_invented(): void
    {
        $this->assertSame('<TitTransEnvio/>', $this->write(null, ''));
    }

    #[Test]
    public function maximum_documented_lengths_are_accepted_without_truncation(): void
    {
        $xml = $this->write(str_repeat('M', 20), str_repeat('D', 39));
        $this->assertStringContainsString(str_repeat('M', 20), $xml);
        $this->assertStringContainsString(str_repeat('D', 39), $xml);
    }

    #[Test]
    public function oversize_manifest_is_rejected_before_any_soap_call(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('idManiCargaArrPaisPart supera 20');
        $this->write(str_repeat('M', 21), null);
    }

    #[Test]
    public function oversize_transport_document_is_rejected_before_any_soap_call(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('idDocTranspArrPaisPart supera 39');
        $this->write(null, str_repeat('D', 40));
    }
}
