<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Container;
use App\Models\ManifestImport;
use App\Models\ShipmentItem;
use App\Services\Parsers\MscVesselNameParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Simple\SimpleXmlGenerator;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Fragmento MSC armado a mano con la estructura del formato. Todos los
 * nombres, identificaciones, contenedores y números son ficticios.
 */
class MscVesselNameParserTest extends TestCase
{
    use BuildsManifestImportSchema;

    private const VESSEL_NAME = 'BARCAZA DEMO';

    /** @var string[] */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        Schema::table('voyages', fn (Blueprint $table) => $table->boolean('active')->default(true)->change());
        Schema::table('shipments', fn (Blueprint $table) => $table->boolean('active')->default(true)->change());
        $this->addVessel(self::VESSEL_NAME);
        $this->addContainerTypes(['20GP', '40HC']);
        DB::table('packaging_types')->where('name', 'CONTENEDOR')->update(['code' => 'T']);
        $this->addClient('EXPORTADORA DEMO SA', '99999999991', self::AR);
        $this->addClient('IMPORTADORA EJEMPLO SRL', '9999991', self::PY);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function file(string $content, string $extension = 'txt'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'msc') . '.' . $extension;
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }

    private function line(string $label, string $value = ''): string
    {
        return $label . "\t" . $value . "\t\t\n";
    }

    private function item(string $container, string $size, string $type, string $packages, string $gross, string $tare, string $seal = 'SEAL0001', string $seal2 = ''): string
    {
        return $this->line('NUMBER OUTER PACKAGES', $packages)
            . $this->line('KIND OUTER PACKAGES', 'PALLETS')
            . $this->line('OVER DIMENSION')
            . $this->line('DESCRIPTION', 'MERCADERIA GENERAL DE PRUEBA')
            . $this->line('GROSS WEIGHT', $gross)
            . $this->line('GROSS WEIGHT UNIT', 'kgs')
            . $this->line('MARCS & NUMBERS')
            . $this->line('CONTAINER NR.', $container)
            . $this->line('SEAL NR.', $seal)
            . $this->line('SEAL NR.', $seal2)
            . $this->line('SIZE', $size)
            . $this->line('CONDITION', 'Port to Port')
            . $this->line('SOC CONTAINERS', 'N')
            . $this->line('TYPE CONTAINER', $type)
            . $this->line('TARE', $tare)
            . $this->line('PARTLOAD', 'N')
            . $this->line('IMO', 'N')
            . $this->line('UNNO')
            . $this->line('DG CLASS');
    }

    private function bill(string $number, array $items, string $gross, string $tare, string $packages): string
    {
        $header = $this->line('VESSEL NAME', self::VESSEL_NAME)
            . $this->line('VOYAGE', '0001A')
            . $this->line('LOADING PORT NAME', 'BUENOS AIRES')
            . $this->line('BILL OF LADING', $number)
            . $this->line('DISCHARGE PORT CODE', 'PYASU')
            . $this->line('DISCHARGE PORT NAME', 'ASUNCION')
            . $this->line('SHIPPER', 'EXPORTADORA DEMO SA')
            . $this->line('SHIPPER´S ADRESS', 'CALLE FICTICIA 100, CIUDAD DEMO')
            . $this->line('CONSIGNEE', 'IMPORTADORA EJEMPLO SRL')
            . $this->line('CONSIGNEE´S ADRESS', 'AVENIDA EJEMPLO 200, CIUDAD DEMO')
            . $this->line('NOTIFY', 'IMPORTADORA EJEMPLO SRL')
            . $this->line('NOTIFY´S ADRESS', 'AVENIDA EJEMPLO 200, CIUDAD DEMO')
            . $this->line('OTHER NOTIFY');

        return $header . implode('', $items)
            . $this->line('TOTAL GROSS WEIGHT', $gross)
            . $this->line('TOTAL TARE', $tare)
            . $this->line('TOTAL NUMBER OUTER PACKAGES', $packages)
            . "###FIN BL###\n";
    }

    /**
     * Dos conocimientos y tres mercaderías. TSTU0000001 aparece en los dos
     * conocimientos: un contenedor físico con dos vínculos.
     */
    private function manifest(): string
    {
        $content = $this->bill('DEMO00000001', [
            $this->item('TSTU0000001', '40', 'HC', '10', '10000,000', '3750,00'),
            $this->item('TSTU0000002', '20', 'DV', '5', '5000,000', '2150,00'),
        ], '15000,000', '5900,00', '15')
            . $this->bill('DEMO00000002', [
                $this->item('TSTU0000001', '40', 'HC', '3', '2500,500', '3750,00'),
            ], '2500,500', '3750,00', '3');

        return str_replace("\n", "\r\n", $content);
    }

    private function import(string $path)
    {
        return app(MscVesselNameParser::class)->parse($path, ['vessel_id' => self::VESSEL]);
    }

    public function test_can_parse_accepts_its_format_and_rejects_others(): void
    {
        $parser = app(MscVesselNameParser::class);

        $this->assertTrue($parser->canParse($this->file($this->manifest())));
        $this->assertTrue($parser->canParse($this->file(mb_convert_encoding($this->manifest(), 'ISO-8859-1', 'UTF-8'))));

        $this->assertFalse($parser->canParse($this->file("UNA:+.? '\nUNB+UNOA:2+EMISOR+RECEPTOR+260101:1200+1'\n", 'edi')));
        $this->assertFalse($parser->canParse($this->file("<?xml version=\"1.0\"?>\n<Manifest><Voyage>0001A</Voyage></Manifest>\n", 'xml')));
        $this->assertFalse($parser->canParse($this->file("MANIFIESTO DE CARGA\n" . $this->manifest())));
        $this->assertFalse($parser->canParse($this->file(str_replace('DISCHARGE PORT CODE', 'DISCHARGE PORT', $this->manifest()))));
        $this->assertFalse($parser->canParse(sys_get_temp_dir() . '/no-existe-' . uniqid() . '.txt'));
    }

    public function test_import_creates_bills_items_and_shared_container(): void
    {
        $result = $this->import($this->file($this->manifest()));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame([
            'voyages' => 1, 'shipments' => 1, 'bills_of_lading' => 2, 'shipment_items' => 3,
            'containers' => 2, 'container_shipment_item' => 3, 'clients' => 2,
        ], $this->operationalCounts());

        $shared = DB::table('containers')->where('container_number', 'TSTU0000001')->value('id');
        $this->assertSame(2, DB::table('container_shipment_item')->where('container_id', $shared)->count());
        $this->assertSame(
            ['DEMO00000001', 'DEMO00000002'],
            DB::table('bills_of_lading')->join('shipment_items', 'shipment_items.bill_of_lading_id', '=', 'bills_of_lading.id')
                ->join('container_shipment_item', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
                ->where('container_shipment_item.container_id', $shared)
                ->orderBy('bills_of_lading.bill_number')->pluck('bills_of_lading.bill_number')->all()
        );

        $shipment = DB::table('shipments')->first();
        $voyage = DB::table('voyages')->where('id', $shipment->voyage_id)->first();
        $containerCount = DB::table('bills_of_lading')
            ->join('shipment_items', 'shipment_items.bill_of_lading_id', '=', 'bills_of_lading.id')
            ->join('container_shipment_item', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
            ->where('bills_of_lading.shipment_id', $shipment->id)
            ->distinct()->count('container_shipment_item.container_id');

        $this->assertSame(2, $containerCount);
        $this->assertSame($containerCount, (int) $shipment->containers_loaded);
        $this->assertSame($containerCount, (int) $voyage->total_containers);
        $this->assertSame($containerCount, (int) $voyage->total_containers_loaded);
        $this->assertEquals(17500.5, (float) $voyage->total_cargo_weight);
        $this->assertEquals((float) $shipment->cargo_weight_loaded, (float) $voyage->total_cargo_weight);
        $this->assertEquals((float) $shipment->cargo_weight_loaded, (float) $voyage->total_cargo_weight_loaded);

        $this->assertEquals(17500.5, (float) DB::table('voyages')->value('total_cargo_weight_loaded'));
        $this->assertEquals(17500.5, (float) DB::table('shipments')->value('cargo_weight_loaded'));
        $this->assertEquals(2, (int) DB::table('voyages')->value('total_containers_loaded'));

        $import = ManifestImport::sole();
        $this->assertSame('completed', $import->status);
        $this->assertCount(2, $import->created_container_ids);
    }

    public function test_import_resolves_condition_commodity_and_packaging_per_item(): void
    {
        $descriptions = [
            'MERCADERIA NCM: 0801.11.00 HS CODE: 08011100',
            'MERCADERIA HS CODES: 841810/841850',
            'MERCADERIA NCM: 732111, 8414.60.00.000A',
            'MERCADERIA REFERENCIA 841830',
        ];
        $items = [];
        foreach ($descriptions as $index => $description) {
            $items[] = str_replace(
                ['MERCADERIA GENERAL DE PRUEBA', $this->line('CONDITION', 'Port to Port'), $this->line('KIND OUTER PACKAGES', 'PALLETS')],
                [
                    $description,
                    [$this->line('CONDITION', 'H'), $this->line('CONDITION', 'P'), '', $this->line('CONDITION')][$index],
                    [$this->line('KIND OUTER PACKAGES', 'Carton(s)'), $this->line('KIND OUTER PACKAGES', 'EMBALAJE ESPECIAL'), '', $this->line('KIND OUTER PACKAGES')][$index],
                ],
                $this->item('TSTU000000' . ($index + 1), '40', 'HC', '1', '1000,000', '3750,00')
            );
        }

        $result = $this->import($this->file($this->bill('DEMO00000001', $items, '4000,000', '15000,00', '4')));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame([
            'BL DEMO00000001, línea 2: varios códigos NCM/HS diferentes; el código queda vacío.',
            'BL DEMO00000001, línea 3: identificación NCM/HS ambigua; el código queda vacío.',
        ], $result->warnings);

        $importedItems = ShipmentItem::with('packagingType')->orderBy('line_number')->get();
        $this->assertSame($descriptions, $importedItems->pluck('item_description')->all());
        $this->assertSame(['08011100', null, null, null], $importedItems->pluck('commodity_code')->all());
        $this->assertSame(['CT', null, null, null], $importedItems->pluck('packaging_code')->all());

        $generator = (new ReflectionClass(SimpleXmlGenerator::class))->newInstanceWithoutConstructor();
        $packagingCode = new ReflectionMethod($generator, 'micDtaPackagingCode');
        $conditions = ['H', 'P', 'H', 'H'];
        foreach ($importedItems as $index => $item) {
            $pivot = DB::table('container_shipment_item')->where('shipment_item_id', $item->id)->sole();
            $container = Container::findOrFail($pivot->container_id);

            $this->assertSame($conditions[$index], $item->container_condition);
            $this->assertSame($conditions[$index], $container->container_condition);
            $this->assertSame($conditions[$index], $pivot->container_condition);
            $this->assertSame('L', $container->condition);
            $this->assertSame('CONTENEDOR', $item->packagingType->name);
            $this->assertSame($index === 0 ? 'CT' : 'CN', $packagingCode->invoke($generator, $item, 'DEMO00000001', $container));
        }
    }

    public function test_import_uses_resolved_consignee_only_when_notify_is_absent(): void
    {
        $this->addClient('NOTIFICATARIO DEMO SRL', '9999992', self::PY);
        $informed = str_replace(
            $this->line('NOTIFY', 'IMPORTADORA EJEMPLO SRL'),
            $this->line('NOTIFY', 'NOTIFICATARIO DEMO SRL'),
            $this->bill('DEMO00000001', [
                $this->item('TSTU0000001', '40', 'HC', '1', '1000,000', '3750,00'),
            ], '1000,000', '3750,00', '1')
        );
        $absent = str_replace(
            [$this->line('NOTIFY', 'IMPORTADORA EJEMPLO SRL'), $this->line('NOTIFY´S ADRESS', 'AVENIDA EJEMPLO 200, CIUDAD DEMO')],
            '',
            $this->bill('DEMO00000002', [
                $this->item('TSTU0000002', '40', 'HC', '1', '1000,000', '3750,00'),
            ], '1000,000', '3750,00', '1')
        );

        $result = $this->import($this->file($informed . $absent));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        [$first, $second] = DB::table('bills_of_lading')->orderBy('id')->get()->all();
        $clients = DB::table('clients')->pluck('id', 'tax_id');
        $this->assertSame($clients['9999992'], $first->notify_party_id);
        $this->assertSame($clients['9999991'], $second->notify_party_id);
        $this->assertSame($second->consignee_id, $second->notify_party_id);
        $this->assertSame(3, DB::table('clients')->count());
    }

    public function test_tax_id_written_after_the_name_goes_to_the_new_client_and_leaves_the_name_clean(): void
    {
        $content = str_replace(
            [
                $this->line('CONSIGNEE', 'IMPORTADORA EJEMPLO SRL'),
                $this->line('NOTIFY', 'IMPORTADORA EJEMPLO SRL'),
            ],
            [
                $this->line('CONSIGNEE', 'LOGISTICA FICTICIA S.A. TAX.ID:99999993-0'),
                $this->line('NOTIFY', 'CARGAS DEMO S.R.L. RUC 99999994-1'),
            ],
            $this->bill('DEMO00000001', [
                $this->item('TSTU0000001', '40', 'HC', '1', '1000,000', '3750,00'),
            ], '1000,000', '3750,00', '1')
        );

        $result = $this->import($this->file($content));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $bill = DB::table('bills_of_lading')->first();
        $consignee = DB::table('clients')->find($bill->consignee_id);
        $notify = DB::table('clients')->find($bill->notify_party_id);
        $this->assertSame('LOGISTICA FICTICIA S.A.', $consignee->legal_name);
        $this->assertSame('999999930', (string) $consignee->tax_id);
        $this->assertSame('CARGAS DEMO S.R.L.', $notify->legal_name);
        $this->assertSame('999999941', (string) $notify->tax_id);
    }

    public function test_file_seals_reach_the_shipper_seal_read_by_micdta(): void
    {
        $content = $this->bill('DEMO00000001', [
            $this->item('TSTU0000001', '40', 'HC', '10', '10000,000', '3750,00', 'QA500001', 'QA500002 QA500003'),
            $this->item('TSTU0000002', '20', 'DV', '5', '5000,000', '2150,00', 'QA500004'),
        ], '15000,000', '5900,00', '15')
            . $this->bill('DEMO00000002', [
                $this->item('TSTU0000001', '40', 'HC', '3', '2500,500', '3750,00', 'QA500001', 'QA500005'),
            ], '2500,500', '3750,00', '3');

        $result = $this->import($this->file(str_replace("\n", "\r\n", $content)));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame('QA500001 QA500002 QA500003 QA500005', Container::where('container_number', 'TSTU0000001')->value('shipper_seal'));
        $this->assertSame('QA500004', Container::where('container_number', 'TSTU0000002')->value('shipper_seal'));
        $this->assertNull(Container::where('container_number', 'TSTU0000001')->value('customs_seal'));
        $this->assertSame([], array_values(array_filter($result->warnings, fn ($w) => str_contains($w, 'precinto'))));
    }

    public function test_long_seal_is_kept_whole_with_a_warning(): void
    {
        $content = $this->bill('DEMO00000001', [
            $this->item('TSTU0000001', '40', 'HC', '1', '1000,000', '3750,00', 'QA6000000000000001'),
        ], '1000,000', '3750,00', '1');

        $result = $this->import($this->file(str_replace("\n", "\r\n", $content)));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame('QA6000000000000001', Container::where('container_number', 'TSTU0000001')->value('shipper_seal'));
        $this->assertContains('Contenedor TSTU0000001: el precinto QA6000000000000001 supera 15 caracteres.', $result->warnings);
    }

    public function test_existing_container_seal_is_not_changed_and_is_reported(): void
    {
        $this->addPreviousVoyage('TSTU0000001', '40HC');

        $result = $this->import($this->file($this->manifest()));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertNull(Container::where('container_number', 'TSTU0000001')->value('shipper_seal'));
        $this->assertSame('SEAL0001', Container::where('container_number', 'TSTU0000002')->value('shipper_seal'));
        $this->assertContains('Contenedor TSTU0000001: ya existía y no tiene el precinto SEAL0001 del archivo; no se modificó.', $result->warnings);
    }

    public function test_unknown_loading_port_fails_with_clear_message_and_creates_nothing(): void
    {
        $before = $this->operationalCounts();
        $ports = DB::table('ports')->count();

        $result = $this->import($this->file(str_replace('BUENOS AIRES', 'PUERTO INEXISTENTE', $this->manifest())));

        $this->assertFalse($result->success);
        $this->assertSame(
            ['MSC: puerto de carga inexistente o ambiguo. Revise el catálogo. Sugerencias: ARBUE (BUENOS AIRES), PYASU (ASUNCION).'],
            $result->errors
        );
        $this->assertSame($before, $this->operationalCounts());
        $this->assertSame($ports, DB::table('ports')->count());
        $this->assertSame(0, ManifestImport::count());
    }

    public function test_unknown_discharge_port_code_fails_with_clear_message_and_creates_nothing(): void
    {
        $before = $this->operationalCounts();
        $ports = DB::table('ports')->count();

        $result = $this->import($this->file(str_replace("PORT CODE\tPYASU", "PORT CODE\tPYXXX", $this->manifest())));

        $this->assertFalse($result->success);
        $this->assertCount(1, $result->errors);
        $this->assertStringStartsWith("El código de puerto 'PYXXX' no está en el catálogo del sistema. ", $result->errors[0]);
        $this->assertStringContainsString('Puertos parecidos: PYASU (ASUNCION).', $result->errors[0]);
        $this->assertStringContainsString('no se dan de alta automáticamente', $result->errors[0]);
        $this->assertSame($before, $this->operationalCounts());
        $this->assertSame($ports, DB::table('ports')->count());
        $this->assertSame(0, ManifestImport::count());
    }

    public function test_revert_leaves_operational_data_as_before(): void
    {
        $this->addPreviousVoyage('TSTU0000001', '40HC');
        $before = $this->operationalSnapshot();

        $this->assertTrue($this->import($this->file($this->manifest()))->success);
        $this->assertSame(4, DB::table('container_shipment_item')->count());
        $this->assertSame(2, DB::table('containers')->count());

        $this->revertImport(ManifestImport::sole());

        $this->assertSame($before, $this->operationalSnapshot());
    }

    public function test_reimport_of_the_same_file_is_rejected_without_changes(): void
    {
        $path = $this->file($this->manifest());
        $this->assertTrue($this->import($path)->success);
        $before = $this->operationalCounts();

        $result = $this->import($path);

        $this->assertFalse($result->success);
        $this->assertSame(['El archivo ya fue importado. Revise el historial de importaciones.'], $result->errors);
        $this->assertSame($before, $this->operationalCounts());
        $this->assertSame(1, ManifestImport::count());
    }
}
