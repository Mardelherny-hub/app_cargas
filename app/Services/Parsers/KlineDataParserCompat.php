<?php

namespace App\Services\Parsers;

/**
 * K-Line conserva su parser base y sólo completa el número de viaje desde la
 * importación cuando el archivo no lo informa.
 */
class KlineDataParserCompat extends KlineDataParser
{
    protected function resolveVoyageNumber(
        array $voyageInfo,
        array $options = []
    ): string {
        $operatorNumber = trim(
            (string) ($options['voyage_number'] ?? '')
        );

        $sourceNumber = trim(
            (string) ($voyageInfo['voyage_number'] ?? '')
        );

        $number = $sourceNumber !== ''
            ? $sourceNumber
            : $operatorNumber;

        if ($number === '') {
            throw new \DomainException(
                'K-Line no informa número de viaje. '
                . 'Debe ingresarlo al importar el manifiesto.'
            );
        }

        if (
            str_starts_with(
                strtoupper($number),
                'KLINE-'
            )
        ) {
            return $number;
        }

        return 'KLINE-' . $number;
    }
}
