<?php

namespace Tests\Unit\Views;

use App\Models\ManifestImport;
use App\Models\Voyage;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Historial de importaciones: con registros vigentes en AFIP, las acciones de
 * reversión se muestran deshabilitadas con el motivo. Datos ficticios.
 */
class ImportHistoryRevertLockTest extends TestCase
{
    private const REASON = 'La embarcación BARCAZA QA tiene registros vigentes en AFIP para este viaje: MIC/DTA M1 registrado en AFIP sin anulación aprobada.';

    private function menu(array $revertLocks, array $voyageRevertLocks): string
    {
        $source = file_get_contents(resource_path('views/company/manifests/import-history.blade.php'));
        $start = strpos($source, '@php($isAddedImport');
        $end = strpos($source, '@if($import->reverted_at)', $start);

        $voyage = new Voyage(['voyage_number' => 'V-QA', 'status' => 'planning']);
        $import = new ManifestImport(['file_name' => 'agregado.txt', 'can_be_reverted' => true]);
        $import->id = 7;
        $import->voyage_id = 3;
        $import->parser_config = ['target_voyage' => ['voyage_id' => 3]];
        $import->setRelation('voyage', $voyage);
        $import->setRelation('importTracking', null);

        return Blade::render(substr($source, $start, $end - $start), [
            'import' => $import,
            'voyagesWithAddedImports' => [3],
            'revertLocks' => $revertLocks,
            'voyageRevertLocks' => $voyageRevertLocks,
        ]);
    }

    public function test_locked_actions_are_disabled_with_the_reason(): void
    {
        $html = $this->menu([7 => self::REASON], [3 => self::REASON]);

        $this->assertStringNotContainsString('openRevertModal', $html);
        $this->assertSame(2, substr_count($html, 'type="button" disabled'));
        $this->assertSame(2, substr_count($html, 'No disponible: ' . self::REASON));
        $this->assertStringContainsString('Revertir lo agregado', $html);
        $this->assertStringContainsString('Revertir viaje completo', $html);
    }

    public function test_unlocked_actions_open_the_confirmation(): void
    {
        $html = $this->menu([7 => null], [3 => null]);

        $this->assertSame(2, substr_count($html, 'openRevertModal('));
        $this->assertStringNotContainsString('No disponible', $html);
    }
}
