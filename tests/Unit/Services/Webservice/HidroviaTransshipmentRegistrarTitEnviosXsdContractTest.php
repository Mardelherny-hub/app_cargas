<?php

namespace Tests\Unit\Services\Webservice;

use DOMDocument;
use DOMXPath;
use ReflectionMethod;
use Tests\TestCase;

/**
 * XSD real incluido en wgesregsintia2.wsdl del repositorio.
 * Su vigencia en ARCA no está confirmada: no habilita transmisión.
 * Reutiliza fixtures del test de ensamblado para evitar datos divergentes.
 */
class HidroviaTransshipmentRegistrarTitEnviosXsdContractTest extends TestCase
{
    private const NS = 'Ar.Gob.Afip.Dga.wgesregsintia2';
    private const XSD_NS = 'http://www.w3.org/2001/XMLSchema';

    private function schema(): string
    {
        $wsdl = new DOMDocument();
        $this->assertTrue($wsdl->load(base_path('wgesregsintia2.wsdl'), LIBXML_NONET));
        $xpath = new DOMXPath($wsdl);
        $xpath->registerNamespace('s', self::XSD_NS);
        $nodes = $xpath->query('//s:schema[@targetNamespace="' . self::NS . '"]');
        $this->assertCount(1, $nodes);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $schema = $doc->importNode($nodes->item(0), true);
        $doc->appendChild($schema);
        $xmlns = 'http://www.w3.org/2000/xmlns/';
        $schema->setAttributeNS($xmlns, 'xmlns:s', self::XSD_NS);
        $schema->setAttributeNS($xmlns, 'xmlns:tns', self::NS);
        return $doc->saveXML();
    }

    private function operation(bool $containerized): DOMDocument
    {
        $class = HidroviaTransshipmentRegistrarTitEnviosXmlAssemblerTest::class;
        $fixture = new $class('test_two_barges_share_one_title_but_get_distinct_weights_and_customs');
        $bill = (new ReflectionMethod($class, 'bill'))->invoke($fixture, [], $containerized);
        $plan = (new ReflectionMethod($class, 'plan'))->invoke($fixture, $containerized);
        $xml = (new ReflectionMethod($class, 'assemble'))->invoke($fixture, null, $bill, $plan);
        $soap = new DOMDocument();
        $before = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($soap->loadXML($xml, LIBXML_NONET));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($before);
        }
        $xpath = new DOMXPath($soap);
        $xpath->registerNamespace('a', self::NS);
        $operations = $xpath->query('//a:RegistrarTitEnvios');
        $this->assertCount(1, $operations);
        $operation = new DOMDocument('1.0', 'UTF-8');
        $operation->appendChild($operation->importNode($operations->item(0), true));
        return $operation;
    }

    private function validate(DOMDocument $operation): array
    {
        $before = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $ok = $operation->schemaValidateSource($this->schema());
            $errors = array_map(fn ($error) => trim($error->message),
                array_values(array_filter(libxml_get_errors(),
                    fn ($error) => $error->level >= LIBXML_ERR_ERROR)));
            return [$ok, $errors];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($before);
        }
    }

    public function test_carga_suelta_multiples_envios_matches_local_wsdl_schema(): void
    {
        [$valid, $errors] = $this->validate($this->operation(false));
        $this->assertTrue($valid, implode(' | ', $errors));
        $this->assertSame([], $errors);
    }

    public function test_loaded_containers_are_blocked_by_local_wsdl_required_fields(): void
    {
        $operation = $this->operation(true);
        $xpath = new DOMXPath($operation);
        $xpath->registerNamespace('a', self::NS);
        $this->assertCount(2, $xpath->query('//a:Bulto/a:idContenedor'));
        $this->assertCount(0, $xpath->query('//a:Bulto/a:cantBultos'));
        $this->assertCount(0, $xpath->query('//a:Bulto/a:cantBultosTotFrac'));
        [$valid, $errors] = $this->validate($operation);
        $this->assertFalse($valid);
        $this->assertStringContainsString('cantBultos', implode(' | ', $errors));
    }

    public function test_local_wsdl_marks_both_package_counts_required(): void
    {
        $dom = new DOMDocument();
        $before = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($dom->loadXML($this->schema(), LIBXML_NONET));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($before);
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', self::XSD_NS);
        foreach (['cantBultos', 'cantBultosTotFrac'] as $field) {
            $nodes = $xpath->query('//s:complexType[@name="Bulto"]/s:sequence/s:element[@name="' . $field . '"]');
            $this->assertCount(1, $nodes);
            $this->assertSame('1', $nodes->item(0)->getAttribute('minOccurs'));
        }
    }

    public function test_local_schema_requires_financial_amounts_and_allows_optional_annexes(): void
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($this->schema(), LIBXML_NONET));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', self::XSD_NS);
        foreach (['montoFob', 'montoFlete', 'montoSeg'] as $field) {
            $nodes = $xpath->query('//s:complexType[@name="Destinacion"]'
                . '/s:sequence/s:element[@name="' . $field . '"]');
            $this->assertCount(1, $nodes);
            $this->assertSame('1', $nodes->item(0)->getAttribute('minOccurs'));
            $this->assertSame('s:decimal', $nodes->item(0)->getAttribute('type'));
        }
        $annexes = $xpath->query('//s:complexType[@name="Destinacion"]'
            . '/s:sequence/s:element[@name="docAnexos"]');
        $this->assertCount(1, $annexes);
        $this->assertSame('0', $annexes->item(0)->getAttribute('minOccurs'));
    }
}
