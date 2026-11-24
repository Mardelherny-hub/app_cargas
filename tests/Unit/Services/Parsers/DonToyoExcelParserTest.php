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

    public function test_package_quantity_is_assigned_only_to_a_single_container(): void
    {
        $sheets = $this->sheets();
        array_pop($sheets['CONTENEDORES']);
        $result = $this->import($this->workbook($sheets));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $links = DB::table('container_shipment_item')->join('containers', 'containers.id', '=', 'container_shipment_item.container_id')
            ->orderBy('container_number')->pluck('container_shipment_item.package_quantity', 'container_number');

        $this->assertNull($links['TSTU0000001']);
        $this->assertNull($links['TSTU0000002']);
        $this->assertSame(8, (int) $links['TSTU0000003']);
    }

    public function test_seals_separated_by_slash_are_stored_as_separate_seals(): void
    {
        $sheets = $this->sheets();
        $sheets['CONTENEDORES'][1][3] = 'QA0000000001/QA0000002 / QA0000003/QA0000000004';
        $sheets['CONTENEDORES'][2][3] = 'QA0000005/QA0000005';

        $result = $this->import($this->workbook($sheets));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $seals = DB::table('containers')->orderBy('container_number')->pluck('shipper_seal', 'container_number');
        $this->assertSame('QA0000000001 QA0000002 QA0000003 QA0000000004', $seals['TSTU0000001']);
        $this->assertSame('QA0000005', $seals['TSTU0000002']);
        $this->assertSame('SEAL0003', $seals['TSTU0000003']);
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

    public function test_explicit_cartons_and_fardos_use_edifact_codes_with_container_packaging(): void
    {
        $sheets = $this->sheets();
        $sheets['BLS'][1][16] = '178 CARTONS FROZEN MERCHANDISE';
        $sheets['BLS'][2][16] = 'Fardos Hojas de cedron';

        $result = $this->import($this->workbook($sheets));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $containerId = DB::table('packaging_types')->where('name', 'CONTENEDOR')->value('id');
        $items = DB::table('shipment_items')->orderBy('id')->get();
        $this->assertSame([$containerId, $containerId], $items->pluck('packaging_type_id')->all());
        $this->assertSame(['CT', 'BL'], $items->pluck('packaging_code')->all());
        $this->assertSame(['CT', 'BL'], $this->generatedMicDtaCodes());
        $this->assertFalse(collect($result->warnings)->contains(
            fn ($warning) => str_contains($warning, 'tipo de embalaje no informado')
        ));
    }

    public function test_no_explicit_packaging_falls_back_to_cn_and_unknown_text_warns(): void
    {
        $sheets = $this->sheets();
        $sheets['BLS'][2][16] = '100 BAGS OF GOODS';

        $result = $this->import($this->workbook($sheets));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $items = DB::table('shipment_items')->orderBy('id')->get();
        $this->assertSame([null, null], $items->pluck('packaging_code')->all());
        $this->assertSame(['CN', 'CN'], $this->generatedMicDtaCodes());
        $this->assertContains(
            'BL DEMOBL000002: embalaje BAGS sin equivalencia EDIFACT inequívoca; se conserva CONTENEDOR.',
            $result->warnings
        );
        $this->assertSame(
            0,
            collect($result->warnings)->filter(fn ($warning) => str_contains($warning, 'tipo de embalaje no informado'))->count()
        );
    }

    public function test_ambiguous_package_terms_do_not_assign_edifact_code(): void
    {
        $sheets = $this->sheets();
        $sheets['BLS'][1][16] = 'CARTONS AND FARDOS';
        $result = $this->import($this->workbook($sheets));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertNull(DB::table('shipment_items')->orderBy('id')->value('packaging_code'));
        $this->assertSame('CN', $this->generatedMicDtaCodes()[0]);
        $this->assertContains(
            'BL DEMOBL000001: varios tipos de embalaje en la descripción; se conserva CONTENEDOR sin código EDIFACT.',
            $result->warnings
        );
    }

    private function generatedMicDtaCodes(): array
    {
        // El catálogo real usa T; el fixture mínimo del test usa CON.
        DB::table('packaging_types')->where('name', 'CONTENEDOR')->update(['code' => 'T']);
        $generator = (new \ReflectionClass(\App\Services\Simple\SimpleXmlGenerator::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($generator, 'micDtaPackagingCode');
        $method->setAccessible(true);
        $codes = [];
        foreach (\App\Models\ShipmentItem::with(['packagingType', 'billOfLading'])->orderBy('id')->get() as $item) {
            $codes[] = $method->invoke(
                $generator, $item, $item->billOfLading->bill_number, new \App\Models\Container()
            );
        }
        return $codes;
    }

    public function test_ncm_uses_source_column_or_unambiguous_label_but_never_guesses_multiple_codes(): void
    {
        $sheets = $this->sheets();
        $sheets['BLS'][1][6] = '0203.29';
        $sheets['BLS'][1][16] = 'FROZEN BEEF NCM: 0203.29';
        $sheets['BLS'][2][6] = '0000.000';
        $sheets['BLS'][2][16] = 'TWO CODES NCM: 9506.99 NCM: 9506.98';

        $result = $this->import($this->workbook($sheets));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame('0203.29', DB::table('shipment_items')->orderBy('id')->value('commodity_code'));
        $this->assertNull(DB::table('shipment_items')->orderByDesc('id')->value('commodity_code'));
        $this->assertContains('BL DEMOBL000002: la descripción contiene varios códigos NCM/HS; el NCM queda vacío.', $result->warnings);
    }

    public function test_unambiguous_ncm_in_description_is_used_when_source_column_is_empty(): void
    {
        $sheets = $this->sheets();
        $sheets['BLS'][1][16] = 'EQUIPMENT NCM: 9506.99';
        $result = $this->import($this->workbook($sheets));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame('9506.99', DB::table('shipment_items')->orderBy('id')->value('commodity_code'));
    }

    public function test_empty_cargo_own_transport_keeps_all_three_parties_and_house_condition(): void
    {
        $sheets = $this->sheets();
        $sheets['BLS'][1][10] = $sheets['BLS'][1][9];
        $sheets['BLS'][1][16] = 'VACIOS MSC';
        $result = $this->import($this->workbook($sheets));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $bill = DB::table('bills_of_lading')->where('bill_number', 'DEMOBL000001')->first();
        $this->assertNotNull($bill->shipper_id);
        $this->assertEquals($bill->shipper_id, $bill->consignee_id);
        $this->assertEquals($bill->consignee_id, $bill->notify_party_id);
        $this->assertSame('H', DB::table('shipment_items')->where('bill_of_lading_id', $bill->id)->value('container_condition'));
        $this->assertEquals(2, DB::table('containers')->where('container_condition', 'H')->where('condition', 'V')->count());
        $this->assertEquals(2, DB::table('container_shipment_item')->join('shipment_items', 'shipment_items.id', '=', 'container_shipment_item.shipment_item_id')->where('shipment_items.bill_of_lading_id', $bill->id)->where('container_shipment_item.container_condition', 'H')->count());
    }

    public function test_inactive_existing_client_is_assigned_with_warning_without_rejecting(): void
    {
        DB::table('clients')->where('legal_name', 'EXPORTADORA DEMO SA')->update(['status' => 'inactive']);
        $inactiveId = DB::table('clients')->where('legal_name', 'EXPORTADORA DEMO SA')->value('id');
        $activeId = DB::table('clients')->where('legal_name', 'IMPORTADORA EJEMPLO SRL')->value('id');

        $result = $this->import($this->workbook($this->sheets()));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame(2, DB::table('clients')->count());
        $this->assertSame(2, DB::table('bills_of_lading')->where('shipper_id', $inactiveId)->count());
        $this->assertSame(2, DB::table('bills_of_lading')->where('consignee_id', $activeId)->count());
        $this->assertSame('inactive', DB::table('clients')->where('id', $inactiveId)->value('status'));

        $inactiveWarnings = collect($result->warnings)->filter(fn ($warning) => str_contains($warning, 'que está inactiva'));
        $this->assertSame([
            "Cliente EXPORTADORA DEMO SA: se asignó la ficha #{$inactiveId}, que está inactiva. "
            . 'Revisar la ficha o la parte del conocimiento.',
        ], $inactiveWarnings->values()->all());
    }

    public function test_suspended_existing_client_warning_names_its_actual_status(): void
    {
        DB::table('clients')->where('legal_name', 'IMPORTADORA EJEMPLO SRL')->update(['status' => 'suspended']);
        $suspendedId = DB::table('clients')->where('legal_name', 'IMPORTADORA EJEMPLO SRL')->value('id');

        $result = $this->import($this->workbook($this->sheets()));

        $this->assertTrue($result->success, implode(' | ', $result->errors));
        $this->assertSame(2, DB::table('clients')->count());
        $this->assertSame(2, DB::table('bills_of_lading')->where('consignee_id', $suspendedId)->count());
        $this->assertContains(
            "Cliente IMPORTADORA EJEMPLO SRL: se asignó la ficha #{$suspendedId}, que está suspendida. "
            . 'Revisar la ficha o la parte del conocimiento.',
            $result->warnings
        );
        $this->assertFalse(collect($result->warnings)->contains(fn ($warning) => str_contains($warning, 'que está inactiva')));
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
