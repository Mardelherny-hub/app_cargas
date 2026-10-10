<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\CmspEdiParser;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class CmspEdiParserIdDeclaContractTest extends TestCase
{
    public function test_real_315n_rff_ep_is_preserved_as_destination_identifier_source(): void
    {
        $references = $this->referencesFromCuscar(
            "UNH+1+CUSCAR:D:96B:UN'\n"
            . "CNI+1++'\n"
            . "RFF+BM:BUEFNX26P104010'\n"
            . "RFF+PLZ:10073'\n"
            . "RFF+EP:26001TRB3002652H'\n"
            . "GID+1+820:BG:::PLT'\n"
            . "FTX+AAA+++TOTAL ITEMS: 17 PALLET BOX PRODUCTOS FARMACEUTICOS'\n"
        );

        $this->assertSame('BUEFNX26P104010', $references['bill_number']);
        $this->assertSame('10073', $references['permit']);
        $this->assertSame('26001TRB3002652H', $references['export_permit']);
        $this->assertSame(16, mb_strlen($references['export_permit']));
    }

    public function test_real_316s_vacio_marker_is_not_a_real_id_decla(): void
    {
        $references = $this->referencesFromCuscar(
            "UNH+1+CUSCAR:D:96B:UN'\n"
            . "CNI+4++'\n"
            . "RFF+BM:SEGBUE26P102897'\n"
            . "RFF+PLZ:10073'\n"
            . "RFF+EP:VACIO'\n"
            . "GID+1+820:BG:::CBC'\n"
            . "FTX+AAA+++VACIO'\n"
        );

        $this->assertSame('SEGBUE26P102897', $references['bill_number']);
        $this->assertSame('VACIO', $references['export_permit']);
    }

    public function test_parser_persists_real_ep_in_both_documentary_and_micdta_fields_without_truncation(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Parsers/CmspEdiParser.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "strtoupper(\$exportPermit) !== 'VACIO'",
            $source
        );
        $this->assertStringContainsString(
            'mb_strlen($exportPermit) <= 16',
            $source
        );
        $this->assertStringContainsString(
            "'permiso_embarque'          => \$exportPermit !== '' ? \$exportPermit : null",
            $source
        );
        $this->assertStringContainsString(
            "'id_decla'                  => \$idDecla",
            $source
        );
        $this->assertStringNotContainsString(
            'substr($exportPermit',
            $source
        );
    }

    private function referencesFromCuscar(string $content): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cuscar-id-decla-');
        file_put_contents($tmp, $content);

        try {
            $parser = new CmspEdiParser();

            (new ReflectionMethod($parser, 'parseEdiFile'))
                ->invoke($parser, $tmp);
            (new ReflectionMethod($parser, 'extractStructuredData'))
                ->invoke($parser);

            $property = new ReflectionProperty($parser, 'parsedData');
            $data = $property->getValue($parser);

            $this->assertNotEmpty($data['containers']);

            return $data['containers'][0]['references'];
        } finally {
            @unlink($tmp);
        }
    }
}
