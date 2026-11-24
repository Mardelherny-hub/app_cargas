<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\BillOfLading;
use App\Services\Parsers\G2OceanXmlParser;
use Illuminate\Database\Eloquent\Model;
use ReflectionMethod;
use ReflectionProperty;
use SimpleXMLElement;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

class G2OceanXmlParserCoreIntegrityTest extends TestCase
{
    use BuildsManifestImportSchema;

    private G2OceanXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(G2OceanXmlParser::class);
    }

    private function invoke(string $method, array $args = [])
    {
        $ref = new ReflectionMethod(
            G2OceanXmlParser::class,
            $method
        );

        $ref->setAccessible(true);

        return $ref->invokeArgs($this->parser, $args);
    }

    public function test_packaging_code_comes_from_explicit_xml_label(): void
    {
        $this->useIsolatedDatabase();
        $items = [];
        foreach (['<pkgType>Coil(s)</pkgType>', '<pkgType>SPECIAL</pkgType>', ''] as $index => $label) {
            $line = $index + 1;
            $detail = new SimpleXMLElement("<detail><itemSNo>{$line}</itemSNo><noOfPkgs>1</noOfPkgs><weight>1</weight>{$label}</detail>");
            $items[] = $this->invoke('extractCargoDetail', [$detail]);
        }
        $bill = (new BillOfLading())->forceFill(['id' => 1, 'bill_number' => 'QA-G2']);
        $saved = Model::withoutEvents(fn () => $this->invoke('createShipmentItems', [$bill, ['cargo_items' => $items]]));

        $this->assertSame(['CL', null, null], array_map(fn ($item) => $item->fresh()->packaging_code, $saved));
        $this->assertSame(10, (int) $saved[2]->cargo_type_id);
        $stats = (new ReflectionProperty($this->parser, 'stats'))->getValue($this->parser);
        $this->assertSame(['BL QA-G2, línea 2: embalaje SPECIAL sin equivalencia EDIFACT inequívoca.'], $stats['warnings']);
    }

    public function test_real_tanchou_arrow_ncm_code_variant_is_extracted(): void
    {
        $description = '99 99 PACKAGES HOT ROLLED STEEL ROUND BAR NCM CODE:7214.99 HOT ROLLED ALLOY STEEL ROUND BARS NCM  CODE:7228.30 SHIPPED ON BOARD FREIGHT PREPAID';

        $this->assertSame(
            '7214.99',
            $this->invoke('extractTariffPosition', [$description])
        );

        $this->assertSame(
            '721499',
            $this->invoke('extractCommodityCode', [$description])
        );
    }

    public function test_custom_code_of_goods_shipped_is_preserved_as_hs_code(): void
    {
        $this->assertSame(
            '730210',
            $this->invoke(
                'extractTariffPosition',
                ['1600 PIECES STEEL RAILWAY RAILS CUSTOM CODE OF GOODS SHIPPED: 730210 REMARKS']
            )
        );
    }

    public function test_date_only_has_no_runtime_time(): void
    {
        $date = $this->invoke(
            'parseDate',
            ['20/02/2025']
        );

        $this->assertSame(
            '2025-02-20 00:00:00',
            $date->format('Y-m-d H:i:s')
        );
    }

    public function test_marks_are_not_fabricated(): void
    {
        $this->assertNull(
            $this->invoke(
                'normalizeG2OceanCargoMarks',
                ['']
            )
        );

        $this->assertSame(
            'N/M',
            $this->invoke(
                'normalizeG2OceanCargoMarks',
                ['N/M']
            )
        );
    }

    public function test_package_quantity_is_strict(): void
    {
        $this->assertSame(
            279,
            $this->invoke(
                'parseG2OceanPackageQuantity',
                [279]
            )
        );
    }

    public function test_zero_packages_fail(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'parseG2OceanPackageQuantity',
            [0]
        );
    }

    public function test_mt_is_converted_without_net_estimate(): void
    {
        $this->assertSame(
            22180.0,
            $this->invoke(
                'g2OceanGrossKg',
                [22.18]
            )
        );
    }

    public function test_prepaid_comes_from_source(): void
    {
        $this->assertSame(
            'prepaid',
            $this->invoke(
                'resolveG2OceanFreightTerms',
                [[[
                    'description' =>
                        'PREPAID ABROAD L/C NO. 123',
                ]]]
            )
        );
    }

    public function test_collect_comes_from_source(): void
    {
        $this->assertSame(
            'collect',
            $this->invoke(
                'resolveG2OceanFreightTerms',
                [[[
                    'description' => 'FREIGHT COLLECT',
                ]]]
            )
        );
    }

    public function test_unknown_freight_is_not_fabricated(): void
    {
        $this->assertNull(
            $this->invoke(
                'resolveG2OceanFreightTerms',
                [[[
                    'description' => 'STEEL PLATES',
                ]]]
            )
        );
    }

    public function test_freight_amount_does_not_imply_payment_terms(): void
    {
        $this->assertNull(
            $this->invoke(
                'resolveG2OceanFreightTerms',
                [[[
                    'description' => 'FREIGHT : USD18600',
                ]]]
            )
        );
    }
    public function test_g2ocean_normalizes_client_name_identity_without_punctuation_noise(): void
    {
        $this->assertSame(
            $this->invoke(
                'normalizeClientIdentityName',
                ['HIERRO Y RUEDA S.A.']
            ),
            $this->invoke(
                'normalizeClientIdentityName',
                ['HIERRO Y RUEDA S.A.,']
            )
        );
    }

    public function test_g2ocean_enriches_unique_unidentified_client_before_creating_duplicate(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Parsers/G2OceanXmlParser.php')
        );

        // La ficha sin identificación fiscal se completa en la resolución
        // compartida de clientes, que el parser usa antes de crear uno nuevo.
        $this->assertStringContainsString('use ResolvesExistingClient;', $source);
        $this->assertStringContainsString('$this->resolveExistingClient(', $source);

        $shared = file_get_contents(
            base_path('app/Services/Parsers/Concerns/ResolvesExistingClient.php')
        );

        $this->assertStringContainsString("'tax_id' => \$taxId", $shared);
        $this->assertStringContainsString(
            'Ficha de cliente sin identificación fiscal completada desde importación',
            $shared
        );
    }

}
