<?php

namespace App\Services\Parsers;

use App\ValueObjects\ManifestParseResult;

class DonToyoExcelParserCompat extends DonToyoExcelParser
{
    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        return parent::parse($filePath, $options);
    }
}
