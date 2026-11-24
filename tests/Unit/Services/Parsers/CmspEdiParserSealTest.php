<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Container;
use App\Models\ShipmentItem;
use App\Services\Parsers\CmspEdiParser;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

class CmspEdiParserSealTest extends TestCase
{
    use BuildsManifestImportSchema;

    public function test_link_of_an_existing_container_keeps_the_seals_of_this_file(): void
    {
        $this->useIsolatedDatabase();
        $container = Container::create(['container_number' => 'ABCU1234567', 'shipper_seal' => 'QA000099']);
        $item = ShipmentItem::create(['bill_of_lading_id' => 1, 'line_number' => 1]);
        $item->setRelation('containers', collect());

        $parser = $this->parserWith(
            "EQD+CN+ABCU1234567+22G1:6346:5++2+5'\n"
            . "SEL+QA500001+CU'\n"
            . "SEL+QA500002+CA'\n"
            . "SEL+QA500003+AB'\n"
        );
        (new ReflectionMethod($parser, 'createContainer'))->invoke($parser, 'ABCU1234567', ['package_info' => '10:PK', 'gross_weight_kg' => 1000], $item);

        $this->assertSame(
            ['QA500003', 'QA500001', 'QA500002'],
            json_decode(DB::table('container_shipment_item')->value('source_seals'), true)
        );
        $this->assertSame('QA000099', $container->fresh()->shipper_seal);
    }

    public function test_container_without_seals_leaves_the_link_without_source_seals(): void
    {
        $parser = $this->parserWith("EQD+CN+ABCU1234567+22G1:6346:5++2+5'\n");

        $this->assertNull((new ReflectionMethod($parser, 'equipmentSourceSeals'))->invoke($parser, 'ABCU1234567'));
    }

    private function parserWith(string $segments): CmspEdiParser
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cuscar-sel-');
        file_put_contents($tmp, "UNH+1+CUSCAR:D:96B:UN'\n" . $segments);

        try {
            $parser = new CmspEdiParser();
            (new ReflectionMethod($parser, 'parseEdiFile'))->invoke($parser, $tmp);
            (new ReflectionMethod($parser, 'extractStructuredData'))->invoke($parser);

            return $parser;
        } finally {
            @unlink($tmp);
        }
    }
    public function test_seal_without_known_issuer_goes_to_shipper_seal(): void
    {
        [$equipment] = $this->parse(
            "EQD+CN+ABCU1234567+22G1:6346:5++2+5'\n"
            . "SEL+QA100001+AB'\n"
            . "EQD+CN+ABCU7654321+22G1:6346:5++2+5'\n"
            . "SEL+QA100002'\n"
        );

        $this->assertSame('QA100001', $equipment['ABCU1234567']['shipper_seal']);
        $this->assertSame([], $equipment['ABCU1234567']['additional_seals']);
        $this->assertSame('QA100002', $equipment['ABCU7654321']['shipper_seal']);
    }

    public function test_declared_issuer_is_respected(): void
    {
        [$equipment] = $this->parse(
            "EQD+CN+ABCU1234567+22G1:6346:5++2+5'\n"
            . "SEL+QA200001+CU'\n"
            . "SEL+QA200002+CA'\n"
            . "SEL+QA200003+SH'\n"
        );

        $this->assertSame('QA200001', $equipment['ABCU1234567']['customs_seal']);
        $this->assertSame('QA200002', $equipment['ABCU1234567']['carrier_seal']);
        $this->assertSame('QA200003', $equipment['ABCU1234567']['shipper_seal']);
    }

    public function test_second_seal_of_the_same_role_stays_in_the_read_field(): void
    {
        [$equipment] = $this->parse(
            "EQD+CN+ABCU1234567+22G1:6346:5++2+5'\n"
            . "SEL+QA300001+CU'\n"
            . "SEL+QA300002+CU'\n"
            . "SEL+QA300002+CU'\n"
            . "SEL+QA300003+SH'\n"
            . "SEL+QA300004+AB'\n"
        );

        $this->assertSame('QA300001 QA300002', $equipment['ABCU1234567']['customs_seal']);
        $this->assertSame('QA300003 QA300004', $equipment['ABCU1234567']['shipper_seal']);
        $this->assertSame([], $equipment['ABCU1234567']['additional_seals']);
    }

    public function test_long_seal_is_kept_whole_with_a_warning(): void
    {
        [$equipment, $warnings] = $this->parse(
            "EQD+CN+ABCU1234567+22G1:6346:5++2+5'\n"
            . "SEL+QA4000000000000001+AB'\n"
        );

        $this->assertSame('QA4000000000000001', $equipment['ABCU1234567']['shipper_seal']);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('QA4000000000000001 supera 15 caracteres', $warnings[0]);
    }

    private function parse(string $segments): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cuscar-sel-');
        file_put_contents($tmp, "UNH+1+CUSCAR:D:96B:UN'\n" . $segments);

        try {
            $parser = new CmspEdiParser();

            (new ReflectionMethod($parser, 'parseEdiFile'))->invoke($parser, $tmp);
            (new ReflectionMethod($parser, 'extractStructuredData'))->invoke($parser);

            $data = (new ReflectionProperty($parser, 'parsedData'))->getValue($parser);
            $stats = (new ReflectionProperty($parser, 'stats'))->getValue($parser);

            return [$data['equipment'], $stats['warnings']];
        } finally {
            @unlink($tmp);
        }
    }
}
