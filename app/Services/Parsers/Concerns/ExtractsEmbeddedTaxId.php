<?php

namespace App\Services\Parsers\Concerns;

/**
 * Extrae identificadores fiscales (CUIT/RUC/CNPJ/NIT) embebidos dentro de
 * campos de texto (nombre, dirección) cuando no vienen en un campo estructurado.
 *
 * Cada parser decide SOBRE QUÉ CAMPO aplicarlo. No se debe aplicar sobre el
 * archivo completo ni sobre descripciones de mercadería (falsos positivos).
 */
trait ExtractsEmbeddedTaxId
{
    /**
     * Devuelve el identificador fiscal embebido (solo dígitos) o null.
     * No valida dígito verificador; solo formato y longitud plausible.
     */
    protected function extractEmbeddedTaxId(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        // Prefijos conocidos. Los más específicos van primero
        // (RUC / TAX ID antes que RUC suelto).
        $prefixes = 'RUT\s*\/\s*VAT|RUC\s*\/\s*TAX\s?ID|TAX\s?ID|TAXID|VAT|R\.U\.C\.|RUC|CUIT(?:\s*NBR)?|CNPJ|NIT';

        // prefijo + separador opcional (: # espacios) + dígitos con . - /
        $pattern = '/(?:' . $prefixes . ')\s*[:#.]?\s*([0-9][0-9.\-\/]{5,})/i';

        if (!preg_match($pattern, $text, $m)) {
            return null;
        }

        // Normalizar: solo dígitos.
        $digits = preg_replace('/\D/', '', $m[1]);

        if (!$this->isPlausibleTaxId($digits)) {
            return null;
        }

        return $digits;
    }

    /**
     * Resuelve el tax_id según prioridad: estructurado > nombre > dirección.
     * Devuelve solo dígitos, o null si no hay dato real (NO fabrica).
     */
    protected function resolveTaxId(
        ?string $structuredTaxId = null,
        ?string $rawName = null,
        ?string $rawAddress = null
    ): ?string {
        // 1) Campo estructurado tiene prioridad (si trae dígitos plausibles).
        if ($structuredTaxId !== null && trim($structuredTaxId) !== '') {
            $digits = preg_replace('/\D/', '', $structuredTaxId);
            if ($this->isPlausibleTaxId($digits)) {
                return $digits;
            }
        }

        // 2) Embebido en el nombre.
        $fromName = $this->extractEmbeddedTaxId($rawName);
        if ($fromName !== null) {
            return $fromName;
        }

        // 3) Embebido en la dirección.
        return $this->extractEmbeddedTaxId($rawAddress);
    }

    /**
     * Longitud plausible y no compuesto solo por ceros.
     * RUC PY (7-9), NIT CO (9-10), CUIT AR (11), CNPJ BR (14).
     *
     * Antes aceptaba cualquier largo entre 7 y 15, con lo cual un texto como
     * "CUIT 33-70504237-32" pasaba como valido (12 digitos) por captura
     * codiciosa del patron. Un identificador de largo imposible es peor que
     * ninguno: viaja a la aduana y nadie lo nota. Sin forma valida -> null,
     * y el operador lo completa a mano.
     *
     * El 7 se incluye porque la inferencia de pais de los parsers ya trata
     * 7-9 digitos como RUC paraguayo (GuaranExcelParser L1063).
     */
    private function isPlausibleTaxId(string $digits): bool
    {
        if (preg_match('/^0*$/', $digits)) {
            return false;
        }

        return in_array(strlen($digits), [7, 8, 9, 10, 11, 14], true);
    }
}
