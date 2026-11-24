<?php

namespace Tests\Feature;

use App\Models\ImportTracking;
use App\Models\ManifestImport;
use App\Models\Voyage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Detalle de una importación: en español y sin datos internos del
 * importador. Datos ficticios.
 */
class ImportDetailsViewTest extends TestCase
{
    use BuildsManifestImportSchema;

    public function test_detail_hides_parser_internals_and_speaks_spanish(): void
    {
        $this->useIsolatedDatabase();
        Schema::create('import_trackings', function (Blueprint $t) {
            $t->id();
            foreach ((new ImportTracking())->getFillable() as $column) {
                $t->string($column)->nullable();
            }
            $t->timestamps();
        });
        $voyage = Voyage::create(['voyage_number' => 'QA-0092', 'company_id' => self::COMPANY, 'status' => 'planning']);
        $import = ManifestImport::create([
            'company_id' => self::COMPANY,
            'voyage_id' => $voyage->id,
            'file_name' => 'qa_20261124_abc.txt',
            'file_format' => 'tfp',
            'status' => 'completed',
            'notes' => 'Importación TFP completada',
            'parser_config' => ['parser_class' => 'App\\Services\\Parsers\\TfpTextParser', 'target_voyage' => ['voyage_id' => $voyage->id]],
        ]);

        $source = file_get_contents(resource_path('views/company/manifests/import-details.blade.php'));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>', '<x-slot name="header">', '</x-slot>'], '', $source);
        $html = Blade::render($source, ['import' => $import->fresh()]);

        $this->assertStringNotContainsString('TfpTextParser', $html);
        $this->assertStringNotContainsString('parser_class', $html);
        $this->assertStringNotContainsString('Configuración del parser', $html);
        $this->assertStringNotContainsString('Shipments', $html);
        $this->assertStringContainsString('Cargas', $html);
        $this->assertStringContainsString('Planificación', $html);
        $this->assertStringContainsString('Importación TFP completada', $html);
    }
}
