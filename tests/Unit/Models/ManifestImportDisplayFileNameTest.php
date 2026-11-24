<?php

namespace Tests\Unit\Models;

use App\Models\ImportTracking;
use App\Models\ManifestImport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Las pantallas de importación muestran el nombre con el que el usuario subió
 * el archivo, no el nombre interno con fecha y sufijo aleatorio.
 */
class ManifestImportDisplayFileNameTest extends TestCase
{
    use BuildsManifestImportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        Schema::create('import_trackings', function (Blueprint $t) {
            $t->id();
            foreach ((new ImportTracking())->getFillable() as $column) {
                $t->string($column)->nullable();
            }
            $t->timestamps();
        });
    }

    public function test_uploaded_name_is_shown_instead_of_the_stored_name(): void
    {
        $import = ManifestImport::create(['file_name' => 'navsur-qa_20261124234045_tl1nYQfS.txt', 'status' => 'completed']);
        ImportTracking::create(['uuid' => 'qa-1', 'original_name' => 'navsur_QA.txt', 'manifest_import_id' => $import->id, 'status' => 'completed']);

        $this->assertSame('navsur_QA.txt', $import->fresh()->display_file_name);
    }

    public function test_stored_name_is_the_fallback_without_upload_record(): void
    {
        $import = ManifestImport::create(['file_name' => 'carga-manual.txt', 'status' => 'completed']);

        $this->assertSame('carga-manual.txt', $import->display_file_name);
    }

    public function test_import_screens_use_the_uploaded_name(): void
    {
        $views = dirname(__DIR__, 3) . '/resources/views/company/manifests/';
        foreach (['import-history.blade.php', 'import-details.blade.php'] as $view) {
            $source = file_get_contents($views . $view);
            $this->assertStringNotContainsString('$import->file_name', $source, $view);
            $this->assertStringContainsString('$import->display_file_name', $source, $view);
        }
        $controller = file_get_contents(dirname(__DIR__, 3) . '/app/Http/Controllers/Company/Manifests/ManifestImportController.php');
        $this->assertStringContainsString("'name'        => \$import->display_file_name,", $controller);
    }
}
