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

    /**
     * Prueba el método PRODUCTIVO de ensamblado, no la copia manual candidate().
     * Solo utiliza el fixture sintético del proyecto; no solicita WSAA ni SOAP.
     */
    private function assembledQaCandidate(
        bool $containerized = true,
        ?array $prepared = null,
        ?string $template = null
    ): string {
        $class = HidroviaTransshipmentRegistrarTitEnviosXmlAssemblerTest::class;
        $fixture = new $class('test_two_barges_share_one_title_but_get_distinct_weights_and_customs');
        $bill = (new ReflectionMethod($class, 'bill'))->invoke($fixture, [], $containerized);
        $plan = $prepared ?? (new ReflectionMethod($class, 'plan'))->invoke($fixture, $containerized);
        $customs = (new ReflectionMethod($class, 'customs'))->invoke($fixture);
        $source = $template ?? (new ReflectionMethod($class, 'template'))->invoke($fixture);
        $assembler = new \App\Services\Simple\HidroviaTransshipmentRegistrarTitEnviosXmlAssembler(
            new \App\Services\Simple\HidroviaTransshipmentMultiEnvioXmlGenerator(
                new \App\Services\Simple\HidroviaTransshipmentContainerBultoWriter()
            )
        );

        return $assembler->assembleContainerHomologationCandidate(
            $source, $bill, $plan, $customs
        );
    }

    private function operationFromSoap(string $soap): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadXML($soap, LIBXML_NONET));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = $this->xpath($document);
        $nodes = $xpath->query('//a:RegistrarTitEnvios');
        $this->assertCount(1, $nodes);
        $operation = new DOMDocument('1.0', 'UTF-8');
        $operation->appendChild($operation->importNode($nodes->item(0), true));

        return $operation;
    }

    public function test_actual_qa_assembler_matches_xsd_and_preserves_two_container_shipments(): void
    {
        $operation = $this->operationFromSoap($this->assembledQaCandidate());
        [$valid, $errors] = $this->xsdResult($operation);
        $this->assertTrue($valid, implode(' | ', $errors));
        $this->assertSame([], $errors);

        // Comparar contra la variante 0/1 que ya estaba probada solo en memoria.
        $expected = $this->candidate($this->sourceOperation(), [1, 1]);
        $this->assertSame(
            $expected->saveXML($expected->documentElement),
            $operation->saveXML($operation->documentElement)
        );

        $xpath = $this->xpath($operation);
        $this->assertCount(1, $xpath->query('//a:TitTransEnvio'));
        $this->assertCount(2, $xpath->query('//a:Envio'));
        $this->assertSame('QA-BL-31', $xpath->evaluate('string(//a:TitTransEnvio/a:idTitTrans)'));
        $this->assertSame('QA-TX-001', $xpath->evaluate('string(//a:argRegistrarTitEnviosParam/a:idTransaccion)'));
        $fields = [
            'idContenedor' => ['QA-CONT-51', 'QA-CONT-52'],
            'cantBultos' => ['0', '0'],
            'cantBultosTotFrac' => ['1', '1'],
            'codTipEmbalaje' => ['CN', 'CN'],
            'pesoBruto' => ['40.1000', '60.1500'],
        ];
        foreach ($fields as $field => $expectedValues) {
            $nodes = $xpath->query('//a:Envio//a:Bulto/a:' . $field);
            $this->assertSame($expectedValues, array_map(
                fn ($node) => $node->textContent, iterator_to_array($nodes)
            ), $field);
        }
        $this->assertSame('20123456789',
            $xpath->evaluate('string((//a:Envio)[1]/a:idFiscalATAMIC)'));
        $this->assertSame('20987654321',
            $xpath->evaluate('string((//a:Envio)[2]/a:idFiscalATAMIC)'));
        $this->assertCount(0, $xpath->query('//a:Bulto/a:marcaNro'));
    }

    public function test_actual_qa_candidate_rejects_carga_suelta(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sólo admite contenedores');
        $this->assembledQaCandidate(false);
    }

    public function test_actual_qa_candidate_rejects_repeated_container_allocation(): void
    {
        $class = HidroviaTransshipmentRegistrarTitEnviosXmlAssemblerTest::class;
        $fixture = new $class('test_two_barges_share_one_title_but_get_distinct_weights_and_customs');
        $prepared = (new ReflectionMethod($class, 'plan'))->invoke($fixture, true);
        $prepared['shipments'][1]['lines'][0]['container_id'] = 51;

        $this->expectException(\InvalidArgumentException::class);
        $this->assembledQaCandidate(true, $prepared);
    }

    public function test_actual_qa_candidate_keeps_nonzero_commercial_amounts_blocked(): void
    {
        $class = HidroviaTransshipmentRegistrarTitEnviosXmlAssemblerTest::class;
        $fixture = new $class('test_two_barges_share_one_title_but_get_distinct_weights_and_customs');
        $template = (new ReflectionMethod($class, 'template'))->invoke($fixture);
        $template = str_replace(
            '<montoFob>0.0000</montoFob>',
            '<montoFob>100.0000</montoFob>',
            $template
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reparto de importes comerciales');
        $this->assembledQaCandidate(true, null, $template);
    }

    public function test_actual_qa_candidate_is_not_available_outside_testing(): void
    {
        $previous = app()->environment();
        try {
            app()->detectEnvironment(fn () => 'production');
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('sólo para QA local');
            $this->assembledQaCandidate();
        } finally {
            app()->detectEnvironment(fn () => $previous);
        }
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
