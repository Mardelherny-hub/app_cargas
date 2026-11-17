<?php

namespace App\Services\Parsers;

use App\ValueObjects\ManifestParseResult;

class MscVesselNameParserCompat extends MscVesselNameParser
{
    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        return parent::parse($filePath, $options);
    }
}
