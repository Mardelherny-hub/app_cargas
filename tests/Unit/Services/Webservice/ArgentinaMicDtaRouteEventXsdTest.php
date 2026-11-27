<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Country;
use App\Models\Port;
use App\Models\Voyage;
use App\Services\Simple\SimpleXmlGenerator;
use DOMDocument;
use DOMXPath;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

/**
 * Las rutas del MIC/DTA (rutasInf, RutInf, eventosProg, EventoProg) generadas
 * se validan contra el XSD de wgesregsintia2.wsdl del repositorio. Datos
 * ficticios, sin base de datos.
 */
class ArgentinaMicDtaRouteEventXsdTest extends TestCase
{
    private const NS = 'Ar.Gob.Afip.Dga.wgesregsintia2';
    private const XSD_NS = 'http://www.w3.org/2001/XMLSchema';

    /** Esquema del WSDL con rutasInf como raíz, para validar ese fragmento con sus tipos. */
    private function schema(): string
    {
        $wsdl = new DOMDocument();
        $this->assertTrue($wsdl->load(base_path('wgesregsintia2.wsdl'), LIBXML_NONET));
        $xpath = new DOMXPath($wsdl);
        $xpath->registerNamespace('s', self::XSD_NS);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $schema = $doc->appendChild($doc->importNode($xpath->query('//s:schema[@targetNamespace="' . self::NS . '"]')->item(0), true));
        $schema->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:s', self::XSD_NS);
        $schema->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tns', self::NS);
        $root = $doc->createElementNS(self::XSD_NS, 's:element');
        $root->setAttribute('name', 'rutasInf');
        $root->setAttribute('type', 'tns:ArrayOfRutInf');
        $schema->appendChild($root);

        return $doc->saveXML();
    }

    private function port(string $code, string $name, string $country): Port
    {
        $port = new Port(['code' => $code, 'name' => $name]);
        $port->setRelation('country', new Country(['alpha2_code' => $country]));

        return $port;
    }

    private function routeXml(): string
    {
        $voyage = new Voyage(['voyage_number' => 'QA-0001']);
        $voyage->departure_date = '2026-11-27 08:00:00';
        $voyage->estimated_arrival_date = '2026-11-30 08:00:00';
        $voyage->setRelation('originPort', $this->port('ARBUE', 'BUENOS AIRES', 'AR'));
        $voyage->setRelation('destinationPort', $this->port('PYASU', 'ASUNCION', 'PY'));

        $generator = (new ReflectionClass(SimpleXmlGenerator::class))->newInstanceWithoutConstructor();
        $w = new XMLWriter();
        $w->openMemory();
        $w->startElement('raiz');
        $w->writeAttribute('xmlns', self::NS);
        (new ReflectionMethod($generator, 'writeRutasInf'))->invoke($generator, $w, $voyage, '10073', '001');
        $w->endElement();

        return $w->outputMemory();
    }

    public function test_generated_route_events_match_the_wsdl_schema(): void
    {
        $before = libxml_use_internal_errors(true);
        try {
            // El espacio de nombres de AFIP no es una URI absoluta: libxml lo
            // avisa al leerlo, igual que en el resto de los contratos XSD.
            $source = new DOMDocument();
            $this->assertTrue($source->loadXML($this->routeXml(), LIBXML_NONET));
            $fragment = new DOMDocument('1.0', 'UTF-8');
            $fragment->appendChild($fragment->importNode($source->documentElement->firstChild, true));
            libxml_clear_errors();
            $valid = $fragment->schemaValidateSource($this->schema());
            $errors = array_map(fn ($e) => trim($e->message), libxml_get_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($before);
        }

        $this->assertTrue($valid, implode(' | ', $errors));
        $xpath = new DOMXPath($fragment);
        $xpath->registerNamespace('a', self::NS);
        $events = $xpath->query('//a:EventoProg');
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame(
                ['codPais', 'codAdu', 'codLugOper', 'codCiu', 'fecha', 'id', 'orden'],
                array_map(fn ($node) => $node->localName, iterator_to_array($xpath->query('a:*', $event)))
            );
        }
        $this->assertSame(['10073', '001'], array_map(fn ($n) => $n->textContent, iterator_to_array($xpath->query('//a:EventoProg/a:codLugOper'))));
        $this->assertSame(['ARBUE', 'PYASU'], array_map(fn ($n) => $n->textContent, iterator_to_array($xpath->query('//a:EventoProg/a:codCiu'))));
    }
}
