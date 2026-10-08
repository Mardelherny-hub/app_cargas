<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\TfpTextParser;
use App\Services\Parsers\TfpTextParserCompat;
use ReflectionMethod;
use Tests\TestCase;

class TfpTextParserCoreIntegrityTest extends TestCase
{
    private TfpTextParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(TfpTextParser::class);
    }

    private function invoke(string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod(TfpTextParser::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($this->parser, $args);
    }

    public function test_voyage_key_is_deterministic(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tfp-');
        file_put_contents($file, 'fixture');

        $block = '
            BUQUE: /*REINA DEL PARANA*/
            CODPUERTOCARGA: /*ARBAI*/
            CODPUERTODESCARGA: /*PYPSE*/
        ';

        $data = $this->invoke('extractVoyageData', [$block, $file]);

        $this->assertSame(
            'TFP-' . substr(hash_file('sha256', $file), 0, 16),
            $data['voyage_number']
        );

        unlink($file);
    }

    public function test_route_defines_voyage_cargo_type(): void
    {
        $this->assertSame(
            'export',
            $this->invoke(
                'resolveVoyageCargoTypeCodes',
                ['AR', 'AR', 'PY']
            )
        );

        $this->assertSame(
            'import',
            $this->invoke(
                'resolveVoyageCargoTypeCodes',
                ['AR', 'PY', 'AR']
            )
        );

        $this->assertSame(
            'import',
            $this->invoke(
                'resolveVoyageCargoTypeCodes',
                ['PY', 'AR', 'PY']
            )
        );
    }

    public function test_real_container_types_are_preserved(): void
    {
        $this->assertSame(
            '20GP',
            $this->invoke('findOrCreateContainerType', ['20DV'])->code
        );

        $this->assertSame(
            '40HC',
            $this->invoke('findOrCreateContainerType', ['40HC'])->code
        );
    }

    public function test_unknown_container_type_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke('findOrCreateContainerType', ['40FR']);
    }

    public function test_compat_maps_real_bm_rosa_40ot_without_losing_size(): void
    {
        $parser = app(TfpTextParserCompat::class);
        $ref = new ReflectionMethod(
            TfpTextParserCompat::class,
            'findOrCreateContainerType'
        );
        $ref->setAccessible(true);

        $type = $ref->invoke($parser, '40OT');

        $this->assertContains($type->code, ['40OT', '40GP']);
        $this->assertSame('40', (string) $type->length_feet);
    }

    public function test_empty_tfp_container_condition_defaults_to_house(): void
    {
        $this->assertSame(
            ['condition' => 'L', 'container_condition' => 'H'],
            $this->invoke('mapTfpCondition', [''])
        );

        $this->assertSame(
            ['condition' => 'L', 'container_condition' => 'H'],
            $this->invoke('mapTfpCondition', [null])
        );
    }

    public function test_compat_recognizes_vacio_and_vacios_as_empty_cargo(): void
    {
        $parser = app(TfpTextParserCompat::class);

        $ref = new ReflectionMethod(
            TfpTextParserCompat::class,
            'isEmptyCargoDescription'
        );
        $ref->setAccessible(true);

        $this->assertTrue(
            $ref->invoke($parser, 'VACIO')
        );

        $this->assertTrue(
            $ref->invoke($parser, 'VACIOS')
        );

        $this->assertTrue(
            $ref->invoke($parser, '  vacios  ')
        );

        $this->assertFalse(
            $ref->invoke($parser, 'CONTENEDORES')
        );
    }

    public function test_non_empty_unknown_tfp_condition_is_still_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke('mapTfpCondition', ['X']);
    }

    public function test_tfp_condition_p_is_preserved_for_afip(): void
    {
        $this->assertSame(
            ['condition' => 'L', 'container_condition' => 'P'],
            $this->invoke('mapTfpCondition', ['P'])
        );
    }

    public function test_unknown_condition_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke('mapTfpCondition', ['X']);
    }

    public function test_compat_restores_container_packaging_contract(): void
    {
        $compatSource = file_get_contents(
            base_path('app/Services/Parsers/TfpTextParserCompat.php')
        );

        $this->assertStringContainsString(
            "PackagingType::where('code', 'T')",
            $compatSource
        );

        $this->assertStringContainsString(
            "'package_type_description' => trim(",
            $compatSource
        );

        $baseSource = file_get_contents(
            base_path('app/Services/Parsers/TfpTextParser.php')
        );

        $this->assertStringContainsString(
            "'primary_packaging_type_id' => \$hasContainers",
            $baseSource
        );

        $this->assertStringContainsString(
            "PackagingType::where('code', 'T')",
            $baseSource
        );
    }

    public function test_tfp_allows_missing_notify_without_fabricating_client(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Parsers/TfpTextParser.php')
        );

        $this->assertStringContainsString(
            "'notify_party_id' => \$notify?->id",
            $source
        );

        $this->assertStringContainsString(
            'TFP: BL sin notificatario informado',
            $source
        );

        $this->assertStringNotContainsString(
            "TFP: notificatario ausente en el BL.",
            $source
        );

        $this->assertStringNotContainsString(
            "Notificatario TFP",
            $source
        );
    }
    public function test_tfp_normalizes_client_name_identity_without_punctuation_noise(): void
    {
        $this->assertSame(
            $this->invoke(
                'normalizeClientIdentityName',
                ['ONBOARD LOGISTICS PARAGUAY S.A.']
            ),
            $this->invoke(
                'normalizeClientIdentityName',
                ['ONBOARD LOGISTICS PARAGUAY SA']
            )
        );

        $this->assertSame(
            $this->invoke(
                'normalizeClientIdentityName',
                ['QUIMAFLEX S.R.L.']
            ),
            $this->invoke(
                'normalizeClientIdentityName',
                ['QUIMAFLEX SRL']
            )
        );
    }

    public function test_tfp_enriches_unique_unidentified_client_before_creating_duplicate(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Parsers/TfpTextParser.php')
        );

        $this->assertStringContainsString(
            'findUnidentifiedClientByNameIdentity',
            $source
        );

        $this->assertStringContainsString(
            "'tax_id' => \$normTaxId",
            $source
        );

        $this->assertStringContainsString(
            'ficha histórica enriquecida con identidad fiscal',
            $source
        );
    }

    public function test_compat_extracts_tariff_position_only_from_explicit_tfp_labels(): void
    {
        $parser = app(TfpTextParserCompat::class);
        $ref = new ReflectionMethod(
            TfpTextParserCompat::class,
            'extractTfpTariffPosition'
        );
        $ref->setAccessible(true);

        $this->assertSame(
            '2930.90.39',
            $ref->invoke($parser, 'NCM: 2930.90.39')
        );

        $this->assertSame(
            '281122',
            $ref->invoke($parser, 'HS CODE: 281122')
        );

        $this->assertSame(
            '6104',
            $ref->invoke($parser, 'HS CODES: 6104, 6204, 6109')
        );

        $this->assertSame(
            '3923',
            $ref->invoke(
                $parser,
                "NCM\nDESCRIPTION\n3923\nBOTELLA C/TAPA"
            )
        );

        $this->assertNull(
            $ref->invoke($parser, 'Invoice 281122 without tariff label')
        );
    }

    public function test_compat_maps_v470_40rf_to_existing_40rh_catalog_code(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Parsers/TfpTextParserCompat.php')
        );

        $this->assertStringContainsString(
            "'40RF' => '40RH'",
            $source
        );
    }

    public function test_compat_extracts_only_explicit_tfp_cargo_marks(): void
    {
        $parser = app(TfpTextParserCompat::class);
        $ref = new ReflectionMethod(
            TfpTextParserCompat::class,
            'extractTfpCargoMarks'
        );
        $ref->setAccessible(true);

        $this->assertSame(
            'SEAL: A1834076',
            $ref->invoke(
                $parser,
                'MARKS AND NUMBERS: SEAL: A1834076'
            )
        );

        $this->assertSame(
            'CARTONES YAGUARETE',
            $ref->invoke(
                $parser,
                "SHIPPING MARKS:\nCARTONES YAGUARETE\n4800000445"
            )
        );

        $this->assertSame(
            'ORDER 084/26',
            $ref->invoke(
                $parser,
                "MARKS:\nORDER 084/26\nCUSTOMER PO 24.4 23"
            )
        );

        $this->assertSame(
            'N/M',
            $ref->invoke(
                $parser,
                "WIRE ROD\nN/M\nFREIGHT PREPAID"
            )
        );

        $this->assertNull(
            $ref->invoke(
                $parser,
                'WIRE ROD 12 ROLLS WITHOUT EXPLICIT MARKS'
            )
        );
    }

    public function test_tfp_preserves_parent_loading_port_for_desc_master_identifier(): void
    {
        $header = $this->invoke('parseHeader', [
            "BLNUMERO: /*ROS505*/\n"
            . "BLMARITIMONUMERO: /*266597428*/\n"
            . "CODPUERTOORIGEN: /*MYKLA*/\n"
            . "CODPUERTOCARGA: /*ARBAI*/\n"
            . "CODPUERTODESCARGA: /*PYSEF*/\n",
        ]);

        $this->assertSame('MYKLA', $header['cod_puerto_origen']);

        $source = file_get_contents(
            base_path('app/Services/Parsers/TfpTextParser.php')
        );

        $this->assertStringContainsString(
            "'master_loading_port_code' =>",
            $source
        );
        $this->assertStringContainsString(
            '$masterLoadingPortCode = $sourceMasterLoadingPort !== \'\'',
            $source
        );
        $this->assertStringContainsString(
            ': strtoupper(trim((string) $loadingPort->code));',
            $source
        );
    }

}
