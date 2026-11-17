<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\ManifestImport;
use App\Services\Parsers\MscVesselNameParser;
use Illuminate\Support\Facades\DB;
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

    private function item(string $container, string $size, string $type, string $packages, string $gross, string $tare): string
    {
        return $this->line('NUMBER OUTER PACKAGES', $packages)
            . $this->line('KIND OUTER PACKAGES', 'PALLETS')
            . $this->line('OVER DIMENSION')
            . $this->line('DESCRIPTION', 'MERCADERIA GENERAL DE PRUEBA')
            . $this->line('GROSS WEIGHT', $gross)
            . $this->line('GROSS WEIGHT UNIT', 'kgs')
            . $this->line('MARCS & NUMBERS')
            . $this->line('CONTAINER NR.', $container)
            . $this->line('SEAL NR.', 'SEAL0001')
            . $this->line('SEAL NR.')
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
