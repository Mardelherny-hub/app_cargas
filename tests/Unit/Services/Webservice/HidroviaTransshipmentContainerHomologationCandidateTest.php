<?php

namespace Tests\Unit\Services\Webservice;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Caso CANDIDATO de homologación para contenedores de transbordo.
 *
 * En Stage, las transacciones 158, 218, 371 y 423 (2026, testing)
 * devolvieron Codigo=0 e idTrack con cantBultos=0 y cantBultosTotFrac=1,
 * PERO eran títulos de un solo envío y embalaje ZT con marcaNro.
 * Este caso multibarcaza conserva CN/otras referencias del XML local.
 * Es una variante exclusivamente en memoria: ni autoriza su transmisión
 * ni demuestra que ARCA aceptará esos campos para transbordo.
 * No modifica el generador, no accede a BD ni solicita WSAA.
 */
class HidroviaTransshipmentContainerHomologationCandidateTest extends TestCase
{
    private const NS = 'Ar.Gob.Afip.Dga.wgesregsintia2';

    private function sourceOperation(): DOMDocument
    {
        $class = HidroviaTransshipmentRegistrarTitEnviosXsdContractTest::class;
        $fixture = new $class('test_loaded_containers_are_blocked_by_local_wsdl_required_fields');
        return (new ReflectionMethod($class, 'operation'))->invoke($fixture, true);
    }

    private function schema(): string
    {
        $class = HidroviaTransshipmentRegistrarTitEnviosXsdContractTest::class;
        $fixture = new $class('test_local_wsdl_marks_both_package_counts_required');
        return (new ReflectionMethod($class, 'schema'))->invoke($fixture);
    }

    /** @return array{bool,array<int,string>} */
    private function xsdResult(DOMDocument $operation): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $valid = $operation->schemaValidateSource($this->schema());
            $errors = array_map(fn ($error) => trim($error->message),
                array_values(array_filter(libxml_get_errors(),
                    fn ($error) => $error->level >= LIBXML_ERR_ERROR)));
            return [$valid, $errors];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Inserta sólo en la COPIA de prueba los dos campos que exige el XSD.
     * La alternativa 0/1 surge de un antecedente histórico distinto;
     * no transforma CN en ZT ni inventa pesos, título o contenedores.
     * @param array<int,int> $totals
     */
    private function candidate(DOMDocument $original, array $totals): DOMDocument
    {
        $copy = clone $original;
        $xpath = new DOMXPath($copy);
        $xpath->registerNamespace('a', self::NS);
        $bultos = $xpath->query('//a:RegistrarTitEnvios//a:Bulto');
        $this->assertCount(count($totals), $bultos);
        foreach ($bultos as $index => $bulto) {
            $this->assertInstanceOf(DOMElement::class, $bulto);
            $peso = $xpath->query('./a:pesoBruto', $bulto);
            $this->assertCount(1, $peso);
            $bulto->insertBefore($copy->createElementNS(self::NS, 'cantBultos', '0'), $peso->item(0));
            $bulto->insertBefore($copy->createElementNS(self::NS, 'cantBultosTotFrac', (string) $totals[$index]), $peso->item(0));
        }
        return $copy;
    }

    private function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('a', self::NS);
        return $xpath;
    }

    public function test_original_multibarge_container_xml_is_not_xsd_valid(): void
    {
        $original = $this->sourceOperation();
        $xpath = $this->xpath($original);
        $this->assertCount(1, $xpath->query('//a:TitTransEnvio'));
        $this->assertCount(2, $xpath->query('//a:Envio'));
        $this->assertCount(2, $xpath->query('//a:Bulto/a:idContenedor'));
        $this->assertCount(0, $xpath->query('//a:Bulto/a:cantBultos'));
        $this->assertCount(0, $xpath->query('//a:Bulto/a:cantBultosTotFrac'));
        [$valid, $errors] = $this->xsdResult($original);
        $this->assertFalse($valid);
        $this->assertStringContainsString('cantBultos', implode(' | ', $errors));
    }

    public function test_historical_zero_one_candidate_passes_xsd_but_is_not_customs_approval(): void
    {
        $original = $this->sourceOperation();
        $before = $original->saveXML();
        $candidate = $this->candidate($original, [1, 1]);
        [$valid, $errors] = $this->xsdResult($candidate);
        $this->assertTrue($valid, implode(' | ', $errors));
        $this->assertSame([], $errors);

        $xpath = $this->xpath($candidate);
        $this->assertSame(['0', '0'], array_map(fn ($node) => $node->textContent,
            iterator_to_array($xpath->query('//a:Bulto/a:cantBultos'))));
        $this->assertSame(['1', '1'], array_map(fn ($node) => $node->textContent,
            iterator_to_array($xpath->query('//a:Bulto/a:cantBultosTotFrac'))));
        $this->assertCount(0, $xpath->query('//a:Bulto/a:marcaNro'));
        $this->assertSame(['CN', 'CN'], array_map(fn ($node) => $node->textContent,
            iterator_to_array($xpath->query('//a:Bulto/a:codTipEmbalaje'))));
        $this->assertSame($before, $original->saveXML(), 'El XML original no debe cambiar.');
    }

    public function test_candidate_preserves_each_barge_container_weight_and_ata(): void
    {
        $candidate = $this->candidate($this->sourceOperation(), [1, 1]);
        $xpath = $this->xpath($candidate);
        $this->assertSame('QA-CONT-51', $xpath->evaluate('string((//a:Envio)[1]//a:idContenedor)'));
        $this->assertSame('QA-CONT-52', $xpath->evaluate('string((//a:Envio)[2]//a:idContenedor)'));
        $this->assertSame('40.1000', $xpath->evaluate('string((//a:Envio)[1]//a:pesoBruto)'));
        $this->assertSame('60.1500', $xpath->evaluate('string((//a:Envio)[2]//a:pesoBruto)'));
        $this->assertSame('20123456789', $xpath->evaluate('string((//a:Envio)[1]/a:idFiscalATAMIC)'));
        $this->assertSame('20987654321', $xpath->evaluate('string((//a:Envio)[2]/a:idFiscalATAMIC)'));
        $this->assertSame('QA-BL-31', $xpath->evaluate('string(//a:TitTransEnvio/a:idTitTrans)'));
        $this->assertCount(1, $xpath->query('//a:TitTransEnvio'));
    }

    public function test_xsd_also_accepts_different_real_split_quantities_so_rule_is_not_proven(): void
    {
        // Las cantidades 4/6 son los bultos declarados en el fixture actual.
        // Si ambas variantes pasan XSD, solo ARCA puede definir la semántica.
        [$valid, $errors] = $this->xsdResult(
            $this->candidate($this->sourceOperation(), [4, 6])
        );
        $this->assertTrue($valid, implode(' | ', $errors));
    }

    public function test_absent_or_incomplete_package_pair_still_fails_xsd(): void
    {
        $original = $this->sourceOperation();
        $candidate = $this->candidate($original, [1, 1]);
        $xpath = $this->xpath($candidate);
        $first = $xpath->query('//a:Bulto/a:cantBultosTotFrac')->item(0);
        $first->parentNode->removeChild($first);
        [$valid, $errors] = $this->xsdResult($candidate);
        $this->assertFalse($valid);
        $this->assertStringContainsString('cantBultosTotFrac', implode(' | ', $errors));
    }
}
