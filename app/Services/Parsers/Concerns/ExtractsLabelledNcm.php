<?php

namespace App\Services\Parsers\Concerns;

/**
 * Códigos NCM/HS dentro de la descripción de la mercadería: los rotulados
 * (NCM, HS, HS CODE, NCM NO., H.S.CODE, etc.) y, aparte, los que tienen
 * formato inequívoco de NCM sin rótulo. La descripción no se modifica.
 */
trait ExtractsLabelledNcm
{
    /**
     * Códigos distintos en el orden del texto, solo dígitos. Los dígitos
     * sobrantes se descartan: 8 o más quedan en 8; 6 o 7, en 6; 4 o 5, en 4.
     * Devuelve null si no hay rótulo y [] si hay rótulo sin código legible.
     *
     * @return string[]|null
     */
    protected function labelledNcmCodes(string $text): ?array
    {
        $label = '/(?<![\pL\pN_])(?:N\.?C\.?M\.?|H\.?S\.?)(?:[ \t.\-]*(?:CODES?|NO\.?|OF THE GOODS SHIPPED))?(?![\pL\pN_])/iu';
        if (!preg_match_all($label, $text, $found, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $token = '(\d+(?:\.\d+)*)([A-Z])?(?![\pL\pN_]|[.,]\d)';
        $separator = '(?:[ \t]*(?:[/,;+&-]|\b(?:AND|OR|Y|O)\b)[ \t]*|[ \t]+)';
        $codes = [];
        foreach ($found[0] as [$match, $offset]) {
            $rest = substr($text, $offset + strlen($match));
            if (!preg_match('~^[ \t]*[:#=.\-]*[ \t]*' . $token . '~iu', $rest, $part)
                || ($code = $this->ncmDigits($part[1], $part[2] ?? '')) === null) {
                continue;
            }
            do {
                $codes[] = $code;
                $rest = substr($rest, strlen($part[0]));
            } while (preg_match('~^' . $separator . $token . '~iu', $rest, $part)
                && strlen(str_replace('.', '', $part[1])) >= 6
                && ($code = $this->ncmDigits($part[1], $part[2] ?? '')) !== null);
        }

        return array_values(array_unique($codes));
    }

    /**
     * Códigos NCM/HS sin rótulo, distintos y en el orden del texto. Solo se
     * toman formas inequívocas, con capítulo 01 a 97: con puntos (8714.10,
     * 8714.10.00, 8421.39.90.000U) o de 6 u 8 dígitos seguidos (871410,
     * 87141000). No se toma un número pegado a otra palabra, seguido de una
     * unidad o moneda, ni precedido de moneda, peso o importe. Los de dígitos
     * seguidos tampoco se toman junto a otra palabra con dígitos (series,
     * listas de referencias) ni después de un rótulo de referencia (NO,
     * INVOICE, DATE, L/C, TEL, etc.).
     *
     * @return string[]
     */
    protected function unlabelledNcmCodes(string $text): array
    {
        $chapter = '(?:0[1-9]|[1-8]\d|9[0-7])';
        $token = '/(?:(?<=\s)|(?<=\()|^)(' . $chapter . '\d{2}\.\d{2}(?:\.\d{2}(?:\.\d{3})?)?|' . $chapter . '\d{4}(?:\d{2})?)([A-Z])?'
            . '(?![\pL\pN_]|[.,]\d|[\/\-][\pL\pN])/iu';
        $unit = '/^[ \t]*(?:KGS?|KILOS?|LBS?|TONS?|TNS?|MTS?|M3|CBM|PCS?|PIECES|UNITS?|CTNS?|CARTONS?|PKGS?|PACKAGES|BAGS?|ROLLS?'
            . '|BOX(?:ES)?|PALLETS?|DRUMS?|LTS?|LITERS?|USD|EUR|%)(?![\pL\pN_])/iu';
        $amount = '/(?:USD|US\$|U\$S|EUR|\$|WEIGHT|PESO|GROSS|NET|KGS?|VALUE|VALOR|AMOUNT|TOTAL)[ \t]*[:=]?[ \t]*$/iu';
        $reference = '/(?<![\pL\pN_])(?:NO|NRO|NBR|NUMBER|N[UÚ]MERO|N°|Nº|#|INVOICE|INV|FACTURA|PO|ORDER|PEDIDO|REF|REFERENCE|REFERENCIA|L\/C|LC|TEL|TEL[EÉ]FONO|PHONE'
            . '|FAX|CEL|RUC|CUIT|NIT|CNPJ|BOOKING|SEAL|PRECINTO|DATE|FECHA|PROFORMA|CONTRACT|CONTRATO|MODEL|MODELO|LOT|LOTE|BATCH|SERIAL|SERIE'
            . '|CHASSIS|CHASIS|VIN)(?![\pL\pN_])/iu';

        preg_match_all($token, $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $codes = [];
        foreach ($found as $match) {
            [$raw, $offset] = $match[1];
            $before = substr($text, 0, $offset);
            $after = substr($text, $match[0][1] + strlen($match[0][0]));
            if (preg_match($unit, $after) || preg_match($amount, $before)) {
                continue;
            }
            if (!str_contains($raw, '.')) {
                $previous = array_slice(preg_split('/\s+/u', $before, -1, PREG_SPLIT_NO_EMPTY), -4);
                $next = array_slice(preg_split('/\s+/u', $after, -1, PREG_SPLIT_NO_EMPTY), 0, 1);
                if (preg_grep('/\d/', array_merge(array_slice($previous, -1), $next)) || preg_grep($reference, $previous)) {
                    continue;
                }
            }
            if (($code = $this->ncmDigits($raw, $match[2][0] ?? '')) !== null) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * La letra final de la extensión nacional (8421.39.90.000U) solo se admite
     * con 10 dígitos o más.
     */
    private function ncmDigits(string $raw, string $suffix): ?string
    {
        $digits = str_replace('.', '', $raw);
        $length = strlen($digits);
        if ($suffix !== '' && $length < 10) {
            return null;
        }

        return match (true) {
            $length >= 8 => substr($digits, 0, 8),
            $length >= 6 => substr($digits, 0, 6),
            $length >= 4 => substr($digits, 0, 4),
            default => null,
        };
    }
}
