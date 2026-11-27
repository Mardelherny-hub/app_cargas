<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Container;
use App\Models\ManifestImport;
use App\Models\ShipmentItem;
use App\Services\Parsers\MscVesselNameParser;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Support\Facades\DB;
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
        $this->addVessel(self::VESSEL_NAME);
        $this->addContainerTypes(['20GP', '40HC']);
        DB::table('packaging_types')->where('name', 'CONTENEDOR')->update(['code' => 'T']);
        DB::table('packaging_types')->insert(['code' => 'N', 'name' => 'NO RETORNABLE', 'active' => 1]);
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
            'BL DEMO00000001, línea 2: la descripción trae varios NCM/HS; se cargó 841810. Otros códigos: 841850.',
            'BL DEMO00000001, línea 3: la descripción trae varios NCM/HS; se cargó 732111. Otros códigos: 84146000.',
        ], $result->warnings);

        $importedItems = ShipmentItem::with('packagingType')->orderBy('line_number')->get();
        $this->assertSame($descriptions, $importedItems->pluck('item_description')->all());
        $this->assertSame(['08011100', '841810', '732111', null], $importedItems->pluck('commodity_code')->all());
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
            $this->assertSame('NO RETORNABLE', $item->packagingType->name);
            // NO RETORNABLE no tiene código EDIFACT de 2 caracteres: sin el
            // código del archivo, el MIC/DTA no tiene embalaje para declarar.
            if ($index === 0) {
                $this->assertSame('CT', $packagingCode->invoke($generator, $item, 'DEMO00000001', $container));
                continue;
            }
            try {
                $packagingCode->invoke($generator, $item, 'DEMO00000001', $container);
                $this->fail('Se esperaba falta de codTipEmbalaje.');
            } catch (\Exception $e) {
                $this->assertStringContainsString('falta codTipEmbalaje EDIFACT de 2 caracteres', $e->getMessage());
            }
        }
    }

    public function test_labelled_commodity_code_is_loaded_from_the_description(): void
    {
        // Formas tomadas de archivos MSC reales; textos ficticios.
        $cases = [
            'GEARBOX NCM: 8483409090 PARTS OF GEARBOX NCM: 8483900090' => '84834090',
            'KIDS RIDE ON TOY HS CODE 9503001000' => '95030010',
            'SPEAKER NCM 85.18.22' => '851822',
            'CURTAIN FABRIC NCM: 5407 REFERENCIA 1879' => '5407',
            'BALL BLADDER (NCM :9506.6)' => '9506',
            'CHRISTMAS TREE NCM NO.9505.10(CHRISTMAS TREE)' => '950510',
            'GREIGE CLOTH H.S.CODE:5208.12' => '520812',
            'MIDEA HOOD NCM 8421.39.90.000U AND 8414.60.00.000A' => '84213990',
            'GALVANIZED WIRE 721720' => null,
            'TOOLS HS CODE AS PER INVOICE' => null,
        ];
        $items = [];
        foreach (array_keys($cases) as $index => $description) {
            $items[] = str_replace(
                'MERCADERIA GENERAL DE PRUEBA',
                $description,
                $this->item(sprintf('TSTU%07d', $index + 1), '40', 'HC', '1', '1000,000', '3750,00')
            );
        }

        $result = $this->import($this->file($this->bill('DEMO00000001', $items, '10000,000', '37500,00', '10')));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $importedItems = ShipmentItem::orderBy('id')->get();
        $this->assertSame(array_keys($cases), $importedItems->pluck('item_description')->all());
        $this->assertSame(array_values($cases), $importedItems->pluck('commodity_code')->all());
        $this->assertSame([
            'BL DEMO00000001, línea 1: la descripción trae varios NCM/HS; se cargó 84834090. Otros códigos: 84839000.',
            'BL DEMO00000001, línea 8: la descripción trae varios NCM/HS; se cargó 84213990. Otros códigos: 84146000.',
            'BL DEMO00000001, línea 10: rótulo NCM/HS sin código legible; el código queda vacío.',
        ], $result->warnings);
    }

    public function test_carrier_service_type_is_not_the_container_condition(): void
    {
        // CONDITION trae el tipo de servicio de la naviera; sin H/P propio va House.
        $services = ['Port to Port', 'Port to Door', 'Door to Port', 'Door to Door', 'CY/CY'];
        $items = [];
        foreach ($services as $index => $service) {
            $items[] = str_replace(
                $this->line('CONDITION', 'Port to Port'),
                $this->line('CONDITION', $service),
                $this->item('TSTU000000' . ($index + 1), '40', 'HC', '1', '1000,000', '3750,00')
            );
        }

        $result = $this->import($this->file($this->bill('DEMO00000001', $items, '5000,000', '18750,00', '5')));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $importedItems = ShipmentItem::orderBy('line_number')->get();
        $this->assertCount(5, $importedItems);
        foreach ($importedItems as $item) {
            $pivot = DB::table('container_shipment_item')->where('shipment_item_id', $item->id)->sole();
            $this->assertSame('H', $item->container_condition, $item->item_description);
            $this->assertSame('H', Container::findOrFail($pivot->container_id)->container_condition);
            $this->assertSame('H', $pivot->container_condition);
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

    public function test_bill_keeps_the_file_name_and_address_and_micdta_uses_them(): void
    {
        // Ficha previa del remitente con otro nombre y sin domicilio real.
        $clientId = DB::table('clients')->where('tax_id', '99999999991')->value('id');
        DB::table('clients')->where('id', $clientId)->update(['legal_name' => 'DEMO ARGENTINA', 'commercial_name' => 'DEMO ARGENTINA']);
        DB::table('client_contact_data')->insert([
            'client_id' => $clientId, 'is_primary' => 1, 'active' => 1, 'address_line_1' => 'CUIT 99-99999999-1',
        ]);
        $content = str_replace(
            $this->line('SHIPPER', 'EXPORTADORA DEMO SA'),
            $this->line('SHIPPER', 'EXPORTADORA DEMO SA TAX ID: 99999999991'),
            $this->bill('DEMO00000001', [
                $this->item('TSTU0000001', '40', 'HC', '1', '1000,000', '3750,00'),
            ], '1000,000', '3750,00', '1')
        );

        $result = $this->import($this->file($content));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $bill = \App\Models\BillOfLading::with('shipper')->sole();
        $this->assertSame($clientId, $bill->shipper_id);
        $this->assertSame('DEMO ARGENTINA', $bill->shipper->legal_name);
        $this->assertSame('CUIT 99-99999999-1', DB::table('client_contact_data')->where('client_id', $clientId)->value('address_line_1'));

        $generator = (new ReflectionClass(SimpleXmlGenerator::class))->newInstanceWithoutConstructor();
        $party = (new ReflectionMethod($generator, 'micDtaPartyData'))->invoke($generator, $bill, 'shipper', $bill->shipper);
        $this->assertSame('EXPORTADORA DEMO SA', $party['name']);
        $this->assertSame('CALLE FICTICIA 100, CIUDAD DEMO', $party['address_line_1']);
        $this->assertSame('99999999991', $party['tax_id']);
    }

    public function test_third_weight_decimal_reaches_the_declared_weight(): void
    {
        $result = $this->import($this->file($this->bill('DEMO00000001', [
            $this->item('TSTU0000001', '40', 'HC', '1', '1893,748', '3750,00'),
            $this->item('TSTU0000002', '20', 'DV', '1', '5115,615', '2150,00'),
        ], '7009,363', '5900,00', '2')));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $items = ShipmentItem::orderBy('line_number')->get();
        $this->assertSame(['1893.748', '5115.615'], $items->pluck('gross_weight_kg')->all());
        $this->assertSame('7009.363', \App\Models\BillOfLading::sole()->gross_weight_kg);
        $this->assertSame(
            ['1893.748', '5115.615'],
            DB::table('container_shipment_item')->orderBy('shipment_item_id')->pluck('gross_weight_kg')
                ->map(fn ($w) => number_format((float) $w, 3, '.', ''))->all()
        );

        // El ítem editado recalcula el total del BL sin perder el tercer decimal.
        $items[0]->update(['gross_weight_kg' => 1893.749]);
        $this->assertSame('7009.364', \App\Models\BillOfLading::sole()->gross_weight_kg);
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
