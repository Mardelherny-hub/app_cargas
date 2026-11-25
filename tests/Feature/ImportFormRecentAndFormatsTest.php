<?php

namespace Tests\Feature;

use App\Models\ManifestImport;
use App\Services\Parsers\ManifestParserFactory;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Formulario de importación: los formatos se nombran en español y la lista de
 * importaciones recientes muestra las que existen. Datos ficticios.
 */
class ImportFormRecentAndFormatsTest extends TestCase
{
    private function recentBlock(array $data): string
    {
        $source = file_get_contents(resource_path('views/company/manifests/import.blade.php'));
        $start = strpos($source, '<!-- Historial reciente -->');
        $end = strpos($source, "@push('scripts')", $start);

        return Blade::render(substr($source, $start, $end - $start), $data);
    }

    public function test_recent_imports_are_listed_when_they_exist(): void
    {
        $import = new ManifestImport([
            'file_name' => 'navsur-qa_20261124_abc.txt',
            'file_format' => 'navsur',
            'status' => 'completed_with_warnings',
        ]);
        $import->id = 7;
        $import->created_at = now()->setDate(2026, 11, 24)->setTime(10, 30);
        $import->setRelation('importTracking', null);
        $import->setRelation('voyage', null);

        $html = $this->recentBlock(['recentImports' => collect([$import])]);

        $this->assertStringNotContainsString('No hay importaciones recientes', $html);
        $this->assertStringContainsString('navsur-qa_20261124_abc.txt', $html);
        $this->assertStringContainsString('Navsur', $html);
        $this->assertStringContainsString('24/11/2026 10:30', $html);
        $this->assertStringContainsString('Con Advertencias', $html);
    }

    public function test_empty_history_says_so(): void
    {
        $this->assertStringContainsString('No hay importaciones recientes', $this->recentBlock(['recentImports' => collect()]));
    }

    public function test_format_cards_use_spanish_names(): void
    {
        $names = array_column((new ManifestParserFactory())->getSupportedFormats(), 'name');

        $this->assertNotEmpty($names);
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('/\bFormat\b|Vessel Name|EDI/', $name);
        }
        $this->assertContains('Paraná (Excel)', $names);
        $this->assertContains('K-Line (.DAT)', $names);
    }
}
