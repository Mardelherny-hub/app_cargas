<?php

namespace Tests\Unit\Services\Exports;

use App\Models\BillOfLading;
use App\Models\Client;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Port;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Vessel;
use App\Models\Voyage;
use App\Services\Exports\LoginXmlManifestExporter;
use App\Services\Parsers\LoginXmlParser;
use DomainException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SimpleXMLElement;

require_once dirname(__DIR__, 4) . '/app/Services/Exports/LoginXmlManifestExporter.php';

class LoginXmlManifestExporterTest extends TestCase
{
    public function test_generated_xml_is_readable_by_current_login_parser(): void
    {
        $voyage = $this->makeVoyage();
        $xml = (new LoginXmlManifestExporter())->generate($voyage);

        $file = tempnam(sys_get_temp_dir(), 'login-export-') . '.xml';
        file_put_contents($file, $xml);

        try {
            $parser = new LoginXmlParser();
            $this->assertTrue($parser->canParse($file));

            $extract = new ReflectionMethod(LoginXmlParser::class, 'extractDataFromXml');
            $extract->setAccessible(true);

            $raw = $extract->invoke($parser, new SimpleXMLElement($xml));
            $data = $parser->transform($raw);

            $this->assertSame('LGN-007N', $data['voyage']['voyage_number']);
            $this->assertSame('LOG-IN EXPERIENCE', $data['voyage']['vessel_name']);
            $this->assertSame('BUENOS AIRES', $data['voyage']['origin_port']);
            $this->assertSame('SANTOS', $data['voyage']['destination_port']);

            $this->assertCount(1, $data['bills_of_lading']);
            $bill = $data['bills_of_lading'][0];

            $this->assertSame('TEST-LOGIN-001', $bill['bill_number']);
            $this->assertSame('30710530196', $bill['shipper_cuit']);
            $this->assertSame('STEARIC ACID TEST', $bill['cargo_description']);
            $this->assertSame(['382311'], $bill['commodity_codes']);
            $this->assertSame(49040.0, $bill['total_weight_kg']);
            $this->assertSame(48.5, $bill['volume_m3']);
            $this->assertCount(2, $bill['containers']);

            $first = $bill['containers'][0];
            $this->assertSame('MEDU4054461', $first['container_number']);
            $this->assertSame('40GP', $first['container_type']);
            $this->assertSame(3740.0, $first['tare_weight_kg']);
            $this->assertSame(24000.0, $first['net_weight_kg']);
            $this->assertSame(24520.0, $first['gross_weight_kg']);
            $this->assertSame(24520.0, $first['vgm']);
            $this->assertSame(['BAH76606', 'BAH76607'], $first['seals']);
            $this->assertSame(['382311'], $first['ncm_codes']);

            $second = $bill['containers'][1];
            $this->assertSame('CAIU2961858', $second['container_number']);
            $this->assertSame(['FX44141571'], $second['seals']);
        } finally {
            @unlink($file);
        }
    }

    public function test_multiple_items_are_rejected_instead_of_merged(): void
    {
        $voyage = $this->makeVoyage();
        $bill = $voyage->shipments->first()->billsOfLading->first();

        $bill->setRelation('shipmentItems', new Collection([
            $bill->shipmentItems->first(),
            new ShipmentItem([
                'line_number' => 2,
                'item_description' => 'SECOND ITEM',
            ]),
        ]));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'El formato Login admite una sola mercadería comercial por conocimiento'
        );

        (new LoginXmlManifestExporter())->generate($voyage);
    }

    private function makeVoyage(): Voyage
    {
        $voyage = new Voyage(['voyage_number' => 'LGN-007N']);
        $vessel = new Vessel(['name' => 'LOG-IN EXPERIENCE']);

        $shipment = new Shipment();
        $shipment->setRelation('vessel', $vessel);

        $bill = new BillOfLading([
            'bill_number' => 'TEST-LOGIN-001',
            'booking_number' => 'BOOK-7788',
            'type_of_move' => 'FCL/FCL',
            'container_summary' => '2x40GP',
            'cargo_description' => 'STEARIC ACID TEST',
            'cargo_marks' => 'MARKS TEST',
            'source_email' => 'ops@example.com',
            'gross_weight_kg' => 49040,
            'volume_m3' => 48.5,
            'commodity_code' => '382311',
            'commodity_codes' => ['382311'],
            'dangerous_goods_details' => [[
                'un_number' => '1823',
                'imdg_class' => '8',
            ]],
        ]);

        $bill->setRelation('shipper', $this->client('RUCA LOGISTICS S.A.', '30710530196'));
        $bill->setRelation('consignee', $this->client('GOODYEAR DO BRASIL LTDA', '60500246001630'));
        $bill->setRelation('notifyParty', null);
        $bill->setRelation('specificContacts', new Collection());
        $bill->setRelation('loadingPort', new Port(['name' => 'BUENOS AIRES']));
        $bill->setRelation('dischargePort', new Port(['name' => 'SANTOS']));

        $item = new ShipmentItem([
            'line_number' => 1,
            'item_description' => 'STEARIC ACID TEST',
            'commodity_code' => '382311',
            'tariff_position' => '382311',
        ]);

        $item->setRelation('containers', new Collection([
            $this->container(
                'MEDU4054461',
                '40GP',
                3740,
                24000,
                24520,
                24520,
                ['BAH76606', 'BAH76607'],
                [0]
            ),
            $this->container(
                'CAIU2961858',
                '40GP',
                3700,
                24000,
                24520,
                24520,
                ['FX44141571'],
                [1]
            ),
        ]));

        $bill->setRelation('shipmentItems', new Collection([$item]));
        $shipment->setRelation('billsOfLading', new Collection([$bill]));
        $voyage->setRelation('leadVessel', $vessel);
        $voyage->setRelation('shipments', new Collection([$shipment]));

        return $voyage;
    }

    private function client(string $name, string $taxId): Client
    {
        $client = new Client([
            'legal_name' => $name,
            'commercial_name' => $name,
            'tax_id' => $taxId,
        ]);
        $client->setRelation('contactData', new Collection());

        return $client;
    }

    private function container(
        string $number,
        string $type,
        float $tare,
        float $net,
        float $gross,
        float $vgm,
        array $seals,
        array $sourceLines
    ): Container {
        $container = new Container([
            'container_number' => $number,
            'tare_weight_kg' => $tare,
            'additional_seals' => [],
        ]);

        $container->setRelation(
            'containerType',
            new ContainerType(['code' => $type])
        );

        $pivot = new Pivot();
        $pivot->gross_weight_kg = $gross;
        $pivot->net_weight_kg = $net;
        $pivot->verified_gross_mass_kg = $vgm;
        $pivot->source_seals = json_encode($seals);
        $pivot->source_line_numbers = json_encode($sourceLines);

        $container->setRelation('pivot', $pivot);

        return $container;
    }
}
