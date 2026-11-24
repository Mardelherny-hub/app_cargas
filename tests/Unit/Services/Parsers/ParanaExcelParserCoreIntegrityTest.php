<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\ParanaExcelParser;
use App\Models\{BillOfLading, Container};
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

class ParanaExcelParserCoreIntegrityTest extends TestCase
{
    use BuildsManifestImportSchema;

    private ParanaExcelParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(ParanaExcelParser::class);
    }

    private function invoke(string $method, array $args = [])
    {
        $reflection = new ReflectionMethod(
            ParanaExcelParser::class,
            $method
        );
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $this->parser,
            $args
        );
    }

    public function test_source_packaging_and_local_container_fallback(): void
    {
        $this->useIsolatedDatabase();
        DB::table('packaging_types')->where('name', 'CONTENEDOR')->update(['code' => 'T']);
        $container = Container::withoutEvents(fn () => Container::create(['container_number' => 'TSTU0000001']));
        $bill = (new BillOfLading())->forceFill(['id' => 1, 'bill_number' => 'QA-PARANA']);
        $data = ['DESCRIPTION' => 'CARGA QA', 'NUMBER_OF_PACKAGES' => '1', 'CONTAINER_NUMBER' => $container->container_number];
        $generator = (new ReflectionClass(SimpleXmlGenerator::class))->newInstanceWithoutConstructor();
        $micDtaCode = new ReflectionMethod($generator, 'micDtaPackagingCode');

        foreach (['Carton(s)' => 'CT', 'EMBALAJE ESPECIAL' => null, 'CTNS' => 'CT', 'T' => null] as $label => $expected) {
            $item = Model::withoutEvents(fn () => $this->invoke('createShipmentItem', [$bill, $data + ['PACK_TYPE' => $label]]));
            $this->assertSame($expected, $item->fresh()->packaging_code);
            if ($label === 'T') {
                $this->assertSame('T', $item->packagingType->code);
                $this->assertSame(1, DB::table('container_shipment_item')->where('shipment_item_id', $item->id)->count());
                $this->assertSame('CN', $micDtaCode->invoke($generator, $item, $bill->bill_number, $container));
            }
        }
        $warnings = (new ReflectionClass($this->parser))->getProperty('warnings')->getValue($this->parser);
        $this->assertSame(['BL QA-PARANA, línea 2: embalaje EMBALAJE ESPECIAL sin equivalencia EDIFACT inequívoca.'], $warnings);
    }

    public function test_voyage_dates_are_not_invented(): void
    {
        $timing = $this->invoke(
            'buildParanaVoyageTiming'
        );

        $this->assertNull($timing['departure_date']);
        $this->assertNull(
            $timing['estimated_arrival_date']
        );
    }

    public function test_missing_bl_date_remains_missing(): void
    {
        $dates = $this->invoke(
            'buildParanaBillDates',
            [null]
        );

        $this->assertNull($dates['bill_date']);
        $this->assertNull($dates['loading_date']);
    }

    public function test_loading_date_is_not_copied_from_bl_date(): void
    {
        $dates = $this->invoke(
            'buildParanaBillDates',
            ['2025-06-30']
        );

        $this->assertSame(
            '2025-06-30',
            $dates['bill_date']->format('Y-m-d')
        );

        $this->assertNull($dates['loading_date']);
    }

    public function test_packages_are_strict(): void
    {
        $this->assertSame(
            25,
            $this->invoke(
                'parseParanaPackageQuantity',
                ['25']
            )
        );
    }

    public function test_zero_packages_are_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke(
            'parseParanaPackageQuantity',
            ['0']
        );
    }

    public function test_missing_marks_are_not_fabricated(): void
    {
        $this->assertNull(
            $this->invoke(
                'normalizeParanaCargoMarks',
                [null]
            )
        );

        $this->assertNull(
            $this->invoke(
                'normalizeParanaCargoMarks',
                ['N/A']
            )
        );
    }

    public function test_container_status_is_strict(): void
    {
        $full = $this->invoke(
            'mapParanaContainerState',
            ['F']
        );

        $this->assertSame('L', $full['condition']);
        $this->assertSame(
            'loaded',
            $full['operational_status']
        );

        $empty = $this->invoke(
            'mapParanaContainerState',
            ['E']
        );

        $this->assertSame('V', $empty['condition']);
        $this->assertSame(
            'empty',
            $empty['operational_status']
        );
    }

    public function test_real_supported_container_types_resolve(): void
    {
        $this->useIsolatedDatabase();
        $this->addContainerTypes(['20GP', '40GP', '40HC', '40RH', '20TN']);
        $cases = [
            '20DV' => '20GP',
            '40DV' => '40GP',
            '40HC' => '40HC',
            '40RH' => '40RH',
            '20TN' => '20TN',
        ];

        foreach ($cases as $source => $expected) {
            $type = $this->invoke(
                'findExistingContainerType',
                [$source]
            );

            $this->assertSame(
                $expected,
                $type->code,
                $source
            );
        }
    }

    public function test_40fr_remains_unmapped_instead_of_fabricated_as_40hc(): void
    {
        $this->useIsolatedDatabase();
        $this->addContainerTypes(['40HC']);
        $type = $this->invoke(
            'findExistingContainerType',
            ['40FR']
        );

        $this->assertNull($type);
    }

    public function test_unknown_container_type_is_still_rejected(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Tipo de contenedor PARANA 99XX '
            . 'sin equivalencia comprobada en catálogo.'
        );

        $this->invoke(
            'findExistingContainerType',
            ['99XX']
        );
    }

    public function test_missing_weight_remains_null(): void
    {
        $this->assertNull(
            $this->invoke(
                'parseWeight',
                [null]
            )
        );

        $this->assertNull(
            $this->invoke(
                'parseWeight',
                ['']
            )
        );
    }

    public function test_explicit_zero_weight_remains_zero(): void
    {
        $this->assertSame(
            0.0,
            $this->invoke(
                'parseWeight',
                ['0']
            )
        );

        $this->assertSame(
            22500.0,
            $this->invoke(
                'parseWeight',
                ['22500']
            )
        );
    }

    public function test_missing_volume_remains_null(): void
    {
        $this->assertNull(
            $this->invoke(
                'parseVolume',
                [null]
            )
        );

        $this->assertNull(
            $this->invoke(
                'parseVolume',
                ['']
            )
        );
    }

    public function test_explicit_zero_volume_remains_zero(): void
    {
        $this->assertSame(
            0.0,
            $this->invoke(
                'parseVolume',
                ['0']
            )
        );

        $this->assertSame(
            12.5,
            $this->invoke(
                'parseVolume',
                ['12.5']
            )
        );
    }

}
