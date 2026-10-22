<?php

namespace App\Services\Simple;

use App\Models\BillOfLading;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

/**
 * Ensambla N Envios en el SOAP completo YA generado para un único título.
 *
 * La plantilla se obtiene por el circuito autenticado existente, sin alterarlo.
 * No solicita WSAA, no escribe BD y NUNCA envía la solicitud a ARCA.
 * Los importes no nulos requieren una regla aduanera de reparto aún pendiente:
 * no se copian sobre todas las barcazas para evitar duplicarlos.
 * Esta salida es para QA local, todavía no está certificada por XSD/ARCA.
 */
final class HidroviaTransshipmentRegistrarTitEnviosXmlAssembler
{
    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const ARCA_NS = 'Ar.Gob.Afip.Dga.wgesregsintia2';

    public function __construct(
        private readonly HidroviaTransshipmentMultiEnvioXmlGenerator $enviosGenerator
    ) {}

    public function assemble(
        string $templateSoapXml,
        BillOfLading $bill,
        array $prepared,
        array $customsByShipmentId
    ): string {
        // El fragmento exige reparto completo, barcazas diferenciadas y
        // ATA y lugares operativos explícitos para CADA envío.
        $fragment = $this->parse($this->enviosGenerator->createEnviosFragment(
            $bill, $prepared, $customsByShipmentId
        ));
        if ($fragment->documentElement?->localName !== 'envios') {
            throw new InvalidArgumentException('El fragmento multibarcaza debe contener envios.');
        }
        $xml = $this->parse($templateSoapXml);
        $envelope = $xml->documentElement;
        if (!$envelope instanceof DOMElement
            || $envelope->localName !== 'Envelope'
            || $envelope->namespaceURI !== self::SOAP_NS) {
            throw new InvalidArgumentException('La plantilla no es un sobre SOAP de RegistrarTitEnvios.');
        }
        $body = $this->child($envelope, 'Body', self::SOAP_NS);
        $operation = $this->child($body, 'RegistrarTitEnvios', self::ARCA_NS);
        $auth = $this->child($operation, 'argWSAutenticacionEmpresa', self::ARCA_NS);
        foreach (['Token', 'Sign', 'CuitEmpresaConectada'] as $key) {
            if (trim($this->child($auth, $key, self::ARCA_NS)->textContent) === '') {
                throw new InvalidArgumentException("La plantilla no tiene {$key} real.");
            }
        }
        $params = $this->child($operation, 'argRegistrarTitEnviosParam', self::ARCA_NS);
        $transactionId = trim($this->child($params, 'idTransaccion', self::ARCA_NS)->textContent);
        if ($transactionId === '' || mb_strlen($transactionId) > 15) {
            throw new InvalidArgumentException('La plantilla requiere idTransaccion real, máximo 15 caracteres.');
        }
        if ($this->optionalChild($params, 'titulosTransContVacios', self::ARCA_NS) !== null) {
            throw new InvalidArgumentException('No se mezclan títulos de contenedores vacíos en esta operación.');
        }
        $titleList = $this->child($params, 'titulosTransEnvios', self::ARCA_NS);
        $titles = $this->children($titleList, 'TitTransEnvio', self::ARCA_NS);
        if (count($titles) !== 1) {
            throw new InvalidArgumentException('Se requiere una plantilla con exactamente un título de carga.');
        }
        $title = $titles[0];
        $billNumber = trim((string) $bill->bill_number);
        if ($billNumber === '' || mb_strlen($billNumber) > 36
            || trim($this->child($title, 'idTitTrans', self::ARCA_NS)->textContent) !== $billNumber
            || trim($this->child($title, 'codViaTrans', self::ARCA_NS)->textContent) !== '8'
            || trim($this->child($title, 'indFraccTransp', self::ARCA_NS)->textContent) !== 'N') {
            throw new InvalidArgumentException('El título de la plantilla no coincide con el conocimiento no fraccionado.');
        }
        foreach (['remitente', 'consignatario', 'destinatario', 'origen', 'destino'] as $name) {
            $this->child($title, $name, self::ARCA_NS);
        }
        $oldEnvios = $this->child($title, 'envios', self::ARCA_NS);
        $original = $this->children($oldEnvios, 'Envio', self::ARCA_NS);
        if (count($original) !== 1) {
            throw new InvalidArgumentException('La plantilla original debe contener un solo envío del título.');
        }
        $originalDestinations = $this->child($original[0], 'destinaciones', self::ARCA_NS);
        $originalDest = $this->child($originalDestinations, 'Destinacion', self::ARCA_NS);
        if (count($this->children($originalDestinations, 'Destinacion', self::ARCA_NS)) !== 1) {
            throw new InvalidArgumentException('La plantilla contiene destinaciones ambiguas.');
        }
        // El generador histórico informa importes completos por conocimiento.
        // Repetirlos en cada envío produciría una declaración duplicada.
        foreach (['montoFob', 'montoFlete', 'montoSeg'] as $money) {
            $value = trim($this->child($originalDest, $money, self::ARCA_NS)->textContent);
            if (!preg_match('/^0+(?:\.0+)?$/D', $value)) {
                throw new InvalidArgumentException(
                    'Falta definir el reparto de importes comerciales entre barcazas.'
                );
            }
        }
        $sourceItems = $this->child($originalDest, 'items', self::ARCA_NS);
        $this->child($originalDest, 'bultos', self::ARCA_NS);
        $itemsByNumber = [];
        foreach ($this->children($sourceItems, 'Item', self::ARCA_NS) as $item) {
            $number = trim($this->child($item, 'nroItem', self::ARCA_NS)->textContent);
            if ($number === '' || isset($itemsByNumber[$number])) {
                throw new InvalidArgumentException('La plantilla tiene ítems sin número o duplicados.');
            }
            $this->child($item, 'peso', self::ARCA_NS);
            $itemsByNumber[$number] = $item;
        }
        // Un título equivocado puede compartir su número, pero no los ítems.
        if (!$bill->relationLoaded('shipmentItems')
            || $bill->shipmentItems->count() !== count($itemsByNumber)) {
            throw new InvalidArgumentException('La plantilla no coincide con las líneas del conocimiento.');
        }
        foreach ($bill->shipmentItems as $item) {
            if (!isset($itemsByNumber[(string) $item->line_number])) {
                throw new InvalidArgumentException('Falta un ítem real del conocimiento en la plantilla.');
            }
        }

        $newEnvios = $xml->createElementNS(self::ARCA_NS, 'envios');
        foreach ($this->children($fragment->documentElement, 'Envio', '') as $part) {
            $newEnvio = $this->inNamespace($xml, $part, self::ARCA_NS);
            $destinations = $this->child($newEnvio, 'destinaciones', self::ARCA_NS);
            $dest = $this->child($destinations, 'Destinacion', self::ARCA_NS);
            $idDecla = trim($this->child($dest, 'idDecla', self::ARCA_NS)->textContent);
            if ($idDecla !== trim($this->child($originalDest, 'idDecla', self::ARCA_NS)->textContent)) {
                throw new InvalidArgumentException('idDecla difiere entre plantilla y distribución.');
            }
            // Clonar la destinación completa conserva montos, divisas,
            // documentos anexos y su orden original en el contrato existente.
            $completeDest = $originalDest->cloneNode(true);
            $partialItems = $this->child($dest, 'items', self::ARCA_NS);
            $replacementItems = $xml->createElementNS(self::ARCA_NS, 'items');
            foreach ($this->children($partialItems, 'Item', self::ARCA_NS) as $partialItem) {
                $number = trim($this->child($partialItem, 'nroItem', self::ARCA_NS)->textContent);
                if (!isset($itemsByNumber[$number])) {
                    throw new InvalidArgumentException("El reparto menciona un ítem ajeno al título: {$number}.");
                }
                $base = $itemsByNumber[$number];
                $imo = $this->optionalChild($base, 'codIMO', self::ARCA_NS)?->textContent;
                $partialImo = $this->optionalChild($partialItem, 'codIMO', self::ARCA_NS)?->textContent;
                if ($imo !== $partialImo) {
                    throw new InvalidArgumentException('El código IMO del ítem cambió respecto de la plantilla.');
                }
                $mergedItem = $base->cloneNode(true);
                $this->child($mergedItem, 'peso', self::ARCA_NS)->textContent =
                    $this->child($partialItem, 'peso', self::ARCA_NS)->textContent;
                $replacementItems->appendChild($mergedItem);
            }
            $completeDest->replaceChild(
                $replacementItems, $this->child($completeDest, 'items', self::ARCA_NS)
            );
            $completeDest->replaceChild(
                $this->child($dest, 'bultos', self::ARCA_NS)->cloneNode(true),
                $this->child($completeDest, 'bultos', self::ARCA_NS)
            );
            $destinations->replaceChild($completeDest, $dest);
            $newEnvios->appendChild($newEnvio);
        }
        if (count($this->children($newEnvios, 'Envio', self::ARCA_NS))
            !== count($prepared['shipments'] ?? [])) {
            throw new InvalidArgumentException('El número de envíos ensamblados no coincide con el preflight.');
        }
        $title->replaceChild($newEnvios, $oldEnvios);
        return $xml->saveXML();
    }

    /**
     * Candidato EXCLUSIVO para QA: contenedores no fraccionados.
     *
     * El WSDL obliga a informar ambos contadores, aunque el manual indica
     * omitir cantBultos para contenedores de transbordo. Los valores 0/1
     * tienen antecedente de aceptación en Stage para títulos de UN envío,
     * no para multibarcaza. Esto NO certifica aceptación aduanera.
     *
     * No altera assemble(), el MIC/DTA convencional ni transmite SOAP.
     */
    public function assembleContainerHomologationCandidate(
        string $templateSoapXml,
        BillOfLading $bill,
        array $prepared,
        array $customsByShipmentId
    ): string {
        if (!app()->environment('testing')) {
            throw new InvalidArgumentException('El candidato de contenedores es sólo para QA local.');
        }

        $xml = $this->parse($this->assemble(
            $templateSoapXml, $bill, $prepared, $customsByShipmentId
        ));
        $body = $this->child($xml->documentElement, 'Body', self::SOAP_NS);
        $operation = $this->child($body, 'RegistrarTitEnvios', self::ARCA_NS);
        $params = $this->child($operation, 'argRegistrarTitEnviosParam', self::ARCA_NS);
        $titles = $this->child($params, 'titulosTransEnvios', self::ARCA_NS);
        $title = $this->child($titles, 'TitTransEnvio', self::ARCA_NS);
        $envios = $this->children($this->child($title, 'envios', self::ARCA_NS),
            'Envio', self::ARCA_NS);
        if (count($envios) !== count($prepared['shipments'] ?? [])) {
            throw new InvalidArgumentException('El candidato no coincide con las barcazas preparadas.');
        }

        $numbers = [];
        foreach ($envios as $index => $envio) {
            $lines = $prepared['shipments'][$index]['lines'] ?? [];
            $destinations = $this->child($envio, 'destinaciones', self::ARCA_NS);
            $destination = $this->child($destinations, 'Destinacion', self::ARCA_NS);
            $bultos = $this->children(
                $this->child($destination, 'bultos', self::ARCA_NS), 'Bulto', self::ARCA_NS
            );
            if (count($bultos) !== count($lines) || $bultos === []) {
                throw new InvalidArgumentException('Cada contenedor debe tener un Bulto real.');
            }
            foreach ($bultos as $position => $bulto) {
                if (empty($lines[$position]['container_id'])
                    || trim($this->child($bulto, 'codTipEmbalaje', self::ARCA_NS)->textContent) !== 'CN'
                    || trim($this->child($bulto, 'indCargSuelt', self::ARCA_NS)->textContent) !== 'N'
                    || $this->optionalChild($bulto, 'marcaNro', self::ARCA_NS) !== null
                    || $this->optionalChild($bulto, 'cantBultos', self::ARCA_NS) !== null
                    || $this->optionalChild($bulto, 'cantBultosTotFrac', self::ARCA_NS) !== null) {
                    throw new InvalidArgumentException('El candidato sólo admite contenedores de carga identificados.');
                }
                $number = trim($this->child($bulto, 'idContenedor', self::ARCA_NS)->textContent);
                if ($number === '' || isset($numbers[$number])) {
                    throw new InvalidArgumentException('El candidato tiene un contenedor repetido o vacío.');
                }
                $numbers[$number] = true;

                // Cada Bulto representa exactamente un idContenedor, no el
                // número de paquetes internos ni los totales del conocimiento.
                $weight = $this->child($bulto, 'pesoBruto', self::ARCA_NS);
                $bulto->insertBefore($xml->createElementNS(self::ARCA_NS,
                    'cantBultos', '0'), $weight);
                $bulto->insertBefore($xml->createElementNS(self::ARCA_NS,
                    'cantBultosTotFrac', '1'), $weight);
            }
        }

        return $xml->saveXML();
    }

    private function parse(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        if (trim($xml) === '' || !@$document->loadXML($xml, LIBXML_NONET)) {
            throw new InvalidArgumentException('XML de la plantilla o de los envíos inválido.');
        }
        return $document;
    }

    /** @return array<int, DOMElement> */
    private function children(DOMElement $parent, string $name, ?string $ns): array
    {
        $found = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name
                && ($ns === null || ($node->namespaceURI ?? '') === $ns)) {
                $found[] = $node;
            }
        }
        return $found;
    }

    private function child(DOMElement $parent, string $name, ?string $ns): DOMElement
    {
        $found = $this->children($parent, $name, $ns);
        if (count($found) !== 1) {
            throw new InvalidArgumentException("Se requiere un único {$name} en el XML.");
        }
        return $found[0];
    }

    private function optionalChild(DOMElement $parent, string $name, ?string $ns): ?DOMElement
    {
        $found = $this->children($parent, $name, $ns);
        if (count($found) > 1) {
            throw new InvalidArgumentException("Elemento {$name} duplicado en el XML.");
        }
        return $found[0] ?? null;
    }

    private function inNamespace(DOMDocument $target, DOMElement $source, string $ns): DOMElement
    {
        $copy = $target->createElementNS($ns, $source->localName);
        foreach ($source->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $copy->appendChild($this->inNamespace($target, $node, $ns));
            } elseif ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
                $copy->appendChild($target->createTextNode($node->textContent));
            }
        }
        return $copy;
    }
}
