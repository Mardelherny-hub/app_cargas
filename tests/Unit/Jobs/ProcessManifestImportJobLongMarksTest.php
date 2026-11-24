<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessManifestImportJob;
use App\Models\BillOfLading;
use App\Models\ManifestImport;
use App\Models\ShipmentItem;
use App\ValueObjects\ManifestParseResult;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Al importar, cualquier formato avisa qué ítems tienen una marca más larga
 * que los 100 caracteres de marcaNro del MIC/DTA, sin recortarla.
 * Datos ficticios.
 */
class ProcessManifestImportJobLongMarksTest extends TestCase
{
    use BuildsManifestImportSchema;

    private function warn(ManifestParseResult $result, ?int $importId): array
    {
        $job = (new ReflectionClass(ProcessManifestImportJob::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($job, 'warnLongCargoMarks');
        $method->setAccessible(true);

        return $method->invoke($job, $result, $importId);
    }

    private function billWithMarks(string $number, array $marksByLine): BillOfLading
    {
        $bill = BillOfLading::create(['bill_number' => $number]);
        foreach ($marksByLine as $line => $marks) {
            ShipmentItem::create([
                'bill_of_lading_id' => $bill->id,
                'line_number' => $line,
                'package_quantity' => 1,
                'cargo_marks' => $marks,
            ]);
        }

        return $bill;
    }

    public function test_marks_longer_than_micdta_limit_are_warned_and_kept_whole(): void
    {
        $this->useIsolatedDatabase();
        $long = str_repeat('M', 101);
        $bill = $this->billWithMarks('QA-BL-1', [
            1 => str_repeat('M', 100) . '   ',
            2 => $long,
            3 => null,
        ]);
        $import = ManifestImport::create([
            'status' => 'completed',
            'warnings' => ['Aviso previo del archivo.'],
            'warnings_count' => 1,
        ]);

        $warnings = $this->warn(ManifestParseResult::success(billsOfLading: [$bill]), $import->id);

        $expected = 'BL QA-BL-1, ítem 2: la marca tiene 101 caracteres y el MIC/DTA admite 100 (marcaNro). No se recortó.';
        $this->assertSame([$expected], $warnings);
        $import->refresh();
        $this->assertSame(['Aviso previo del archivo.', $expected], $import->warnings);
        $this->assertSame(2, (int) $import->warnings_count);
        $this->assertSame('completed_with_warnings', $import->status);
        $this->assertSame($long, ShipmentItem::where('line_number', 2)->value('cargo_marks'));
    }

    public function test_bills_recorded_by_the_import_are_used_when_the_result_has_none(): void
    {
        $this->useIsolatedDatabase();
        $own = $this->billWithMarks('QA-BL-2', [1 => str_repeat('M', 120)]);
        $this->billWithMarks('QA-BL-OTRO', [1 => str_repeat('M', 150)]);
        $import = ManifestImport::create(['status' => 'completed', 'created_bill_ids' => [$own->id]]);

        $warnings = $this->warn(ManifestParseResult::success(), $import->id);

        $this->assertSame(
            ['BL QA-BL-2, ítem 1: la marca tiene 120 caracteres y el MIC/DTA admite 100 (marcaNro). No se recortó.'],
            $warnings
        );
    }

    public function test_marks_within_the_limit_leave_the_import_untouched(): void
    {
        $this->useIsolatedDatabase();
        $bill = $this->billWithMarks('QA-BL-3', [1 => str_repeat('M', 100)]);
        $import = ManifestImport::create(['status' => 'completed']);

        $this->assertSame([], $this->warn(ManifestParseResult::success(billsOfLading: [$bill]), $import->id));
        $this->assertSame('completed', $import->fresh()->status);
    }
}
