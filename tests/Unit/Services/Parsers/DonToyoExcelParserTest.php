<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\ManifestImport;
use App\Services\Parsers\DonToyoExcelParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Libro Don Toyo generado en el test con la estructura del formato. Todos
 * los nombres, identificaciones, contenedores y números son ficticios.
 */
class DonToyoExcelParserTest extends TestCase
{
    use BuildsManifestImportSchema;

    private const BUQUE = ['Armador', 'CODIGO IMO BUQUE:', 'VIAJE:', 'IMPO/EXPO'];
    private const BLS = ['BL', 'Origen', 'Carga', 'Descarga', 'Destino Final', 'CUIT SIM', 'NCM', 'NCM o PE', 'Consolidado',
        'Shipper', 'Cnee', 'Cuit Facturacion', 'NombreFacturacion', 'Volumen', 'Peso (KG)', 'Cantidad', 'DESCRIPCION MERCADERIA'];
    private const CONTENEDORES = ['BL', 'CONTENEDOR', 'TIPO (ISO)', 'Seal', 'Peso', 'Volumen', 'Tara', 'VGM', 'Temperatura'];

    /** @var string[] */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        Schema::table('voyages', fn (Blueprint $table) => $table->boolean('active')->default(true)->change());
        Schema::table('shipments', fn (Blueprint $table) => $table->boolean('active')->default(true)->change());
        $this->addVessel('BARCAZA DEMO');
        $this->addContainerTypes(['22G1', '45G0', '42U1', '42P1']);
        $this->addClient('EXPORTADORA DEMO SA', '9999991', self::PY);
        $this->addClient('IMPORTADORA EJEMPLO SRL', '99999999991', self::AR);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function bill(string $number, float $volume, float $weight, int $quantity): array
    {
        return [$number, 'PYASU', 'PYASU', 'ARBUE', 'ARBUE', '', '', '', 'N',
            'EXPORTADORA DEMO SA', 'IMPORTADORA EJEMPLO SRL', '', '', $volume, $weight, $quantity, 'MERCADERIA GENERAL DE PRUEBA'];
    }

    /**
     * Dos conocimientos y cuatro contenedores, uno por tipo ISO. En el
     * primero, la suma de Peso en CONTENEDORES no coincide con BLS.
     */
    private function sheets(): array
    {
        return [
            'BUQUE' => [self::BUQUE, ['ARMADOR DEMO', '', '0002B', 'I']],
            'BLS' => [
                self::BLS,
                $this->bill('DEMOBL000001', 50.5, 30000.25, 20),
                $this->bill('DEMOBL000002', 20.0, 12000.0, 8),
            ],
            'CONTENEDORES' => [
                self::CONTENEDORES,
                ['DEMOBL000001', 'TSTU0000001', '45G0', 'SEAL0001', 18000, 30.5, 3750, 21750, ''],
                ['DEMOBL000001', 'TSTU0000002', '42U1', 'SEAL0002', 11000, 20.0, 3650, 14650, ''],
                ['DEMOBL000002', 'TSTU0000003', '42P1', 'SEAL0003', 7000, 10.0, 4950, 11950, ''],
                ['DEMOBL000002', 'TSTU0000004', '22G1', 'SEAL0004', 5000, 10.0, 2150, 7150, ''],
            ],
        ];
    }

    private function workbook(array $sheets, string $format = 'Xls'): string
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        foreach ($sheets as $name => $rows) {
            $book->createSheet()->setTitle($name)->fromArray($rows, null, 'A1', true);
        }
        $path = tempnam(sys_get_temp_dir(), 'dontoyo') . '.' . strtolower($format);
        IOFactory::createWriter($book, $format)->save($path);
        $book->disconnectWorksheets();
        $this->files[] = $path;

        return $path;
    }

    private function import(string $path)
    {
        return app(DonToyoExcelParser::class)->parse($path, ['vessel_id' => self::VESSEL]);
    }

    public function test_can_parse_accepts_its_format_and_rejects_others(): void
    {
        $parser = app(DonToyoExcelParser::class);

        $this->assertTrue($parser->canParse($this->workbook($this->sheets())));
        $this->assertTrue($parser->canParse($this->workbook($this->sheets(), 'Xlsx')));

        $other = $this->workbook(['Hoja1' => [['BL', 'CONTENEDOR', 'PESO'], ['DEMOBL000001', 'TSTU0000001', 1000]]]);
        $this->assertFalse($parser->canParse($other));

        $sheets = $this->sheets();
        unset($sheets['CONTENEDORES']);
        $this->assertFalse($parser->canParse($this->workbook($sheets)));

        $sheets = $this->sheets();
        $sheets['BLS'][0][14] = 'Peso';
        $this->assertFalse($parser->canParse($this->workbook($sheets)));

        $text = tempnam(sys_get_temp_dir(), 'dontoyo') . '.txt';
        file_put_contents($text, "VESSEL NAME\tBARCAZA DEMO\nVOYAGE\t0001A\n");
        $this->files[] = $text;
        $this->assertFalse($parser->canParse($text));
    }

    public function test_import_creates_bills_items_containers_with_exact_iso_types(): void
    {
        $result = $this->import($this->workbook($this->sheets()));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame([
            'voyages' => 1, 'shipments' => 1, 'bills_of_lading' => 2, 'shipment_items' => 2,
            'containers' => 4, 'container_shipment_item' => 4, 'clients' => 2,
        ], $this->operationalCounts());

        $physicalContainers = DB::table('bills_of_lading')
            ->join('shipment_items', 'shipment_items.bill_of_lading_id', '=', 'bills_of_lading.id')
            ->join('container_shipment_item', 'container_shipment_item.shipment_item_id', '=', 'shipment_items.id')
            ->distinct()->count('container_shipment_item.container_id');
        $this->assertSame(4, $physicalContainers);
        $this->assertSame(4, (int) DB::table('shipments')->value('containers_loaded'));
        $this->assertSame(4, (int) DB::table('voyages')->value('total_containers'));
        $this->assertSame(4, (int) DB::table('voyages')->value('total_containers_loaded'));
        $this->assertEquals(42000.25, (float) DB::table('shipments')->value('cargo_weight_loaded'));
        $this->assertEquals(42000.25, (float) DB::table('voyages')->value('total_cargo_weight'));
        $this->assertEquals(42000.25, (float) DB::table('voyages')->value('total_cargo_weight_loaded'));

        $this->assertSame([
            'TSTU0000001' => '45G0', 'TSTU0000002' => '42U1', 'TSTU0000003' => '42P1', 'TSTU0000004' => '22G1',
        ], DB::table('containers')->join('container_types', 'container_types.id', '=', 'containers.container_type_id')
            ->orderBy('container_number')->pluck('container_types.iso_code', 'containers.container_number')->all());
    }

    public function test_weight_comes_from_the_bls_sheet(): void
    {
        $result = $this->import($this->workbook($this->sheets()));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        // BLS: 30000,25 + 12000 = 42000,25. CONTENEDORES suma 41000.
        $this->assertEquals(42000.25, (float) DB::table('shipments')->value('cargo_weight_loaded'));
        $this->assertEquals(42000.25, (float) DB::table('voyages')->value('total_cargo_weight'));
        $this->assertEquals(42000.25, (float) DB::table('voyages')->value('total_cargo_weight_loaded'));
        $this->assertEquals(30000.25, (float) DB::table('bills_of_lading')->where('bill_number', 'DEMOBL000001')->value('gross_weight_kg'));
        $this->assertEquals(30000.25, (float) DB::table('shipment_items')->orderBy('id')->value('gross_weight_kg'));
        $this->assertContains(
            'BL DEMOBL000001: el peso de BLS difiere de la suma de Peso en CONTENEDORES; se conservan ambos sin repartir ni sustituir por VGM.',
            $result->warnings
        );
    }

    public function test_unknown_port_fails_with_clear_message_and_creates_nothing(): void
    {
        $before = $this->operationalCounts();
        $ports = DB::table('ports')->count();
        $sheets = $this->sheets();
        $sheets['BLS'][2][3] = 'ARXXX';

        $result = $this->import($this->workbook($sheets));

        $this->assertFalse($result->success);
        $this->assertCount(1, $result->errors);
        $this->assertStringStartsWith("El código de puerto 'ARXXX' no está en el catálogo del sistema. ", $result->errors[0]);
        $this->assertStringContainsString('Puertos parecidos: ARBUE (BUENOS AIRES).', $result->errors[0]);
        $this->assertStringContainsString('no se dan de alta automáticamente', $result->errors[0]);
        $this->assertSame($before, $this->operationalCounts());
        $this->assertSame($ports, DB::table('ports')->count());
        $this->assertSame(0, ManifestImport::count());
    }

    public function test_revert_leaves_operational_data_as_before(): void
    {
        $this->addPreviousVoyage('TSTU0000001', '45G0');
        $before = $this->operationalSnapshot();

        $this->assertTrue($this->import($this->workbook($this->sheets()))->success);
        $this->assertSame(5, DB::table('container_shipment_item')->count());
        $this->assertSame(4, DB::table('containers')->count());

        $this->revertImport(ManifestImport::sole());

        $this->assertSame($before, $this->operationalSnapshot());
    }

    public function test_reimport_of_the_same_file_is_rejected_without_changes(): void
    {
        $path = $this->workbook($this->sheets());
        $this->assertTrue($this->import($path)->success);
        $before = $this->operationalCounts();

        $result = app(DonToyoExcelParser::class)->parse($path, ['vessel_id' => self::VESSEL, 'voyage_number' => 'OTRO-01']);

        $this->assertFalse($result->success);
        $this->assertSame(['El archivo ya fue importado. Revise el historial de importaciones.'], $result->errors);
        $this->assertSame($before, $this->operationalCounts());
        $this->assertSame(1, ManifestImport::count());
    }
}
