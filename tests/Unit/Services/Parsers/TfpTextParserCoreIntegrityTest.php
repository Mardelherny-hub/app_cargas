<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Port;
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
        $ar = Port::where('code', 'ARBUE')->firstOrFail();
        $py = Port::where('code', 'PYPSE')->firstOrFail();

        $this->assertSame(
            'export',
            $this->invoke('resolveTfpVoyageCargoType', [$ar, $py])
        );

        $this->assertSame(
            'import',
            $this->invoke('resolveTfpVoyageCargoType', [$py, $ar])
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

}
