<?php

namespace App\Services\Parsers\Concerns;

/**
 * Códigos NCM/HS rotulados dentro de la descripción de la mercadería (NCM, HS,
 * HS CODE, NCM NO., H.S.CODE, etc.). Un número sin rótulo no se toma: puede
 * ser un peso, una cantidad o una referencia. La descripción no se modifica.
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
