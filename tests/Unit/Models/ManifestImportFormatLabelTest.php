<?php

namespace Tests\Unit\Models;

use App\Models\ManifestImport;
use Tests\TestCase;

/**
 * El historial muestra y filtra todos los formatos con nombres legibles,
 * no con el código interno guardado en file_format.
 */
class ManifestImportFormatLabelTest extends TestCase
{
    public function test_every_format_written_by_a_parser_has_a_readable_name(): void
    {
        $formats = [];
        foreach (glob(app_path('Services/Parsers/*.php')) as $file) {
            preg_match_all("/'file_format'\\s*=>\\s*'([a-z0-9_]+)'/", file_get_contents($file), $matches);
            $formats = array_merge($formats, $matches[1]);
        }
        $formats = array_values(array_unique($formats));

        $this->assertNotEmpty($formats);
        foreach ($formats as $format) {
            $this->assertArrayHasKey($format, ManifestImport::FORMAT_LABELS, $format);
        }
        $this->assertSame('Guaraní', (new ManifestImport(['file_format' => 'guaran_excel']))->format_label);
        $this->assertSame('MSC', (new ManifestImport(['file_format' => 'msc_vessel_name']))->format_label);
    }

    public function test_history_filters_by_the_stored_format_with_every_option(): void
    {
        $source = file_get_contents(resource_path('views/company/manifests/import-history.blade.php'));

        $this->assertStringContainsString('ManifestImport::FORMAT_LABELS as $formatValue => $formatLabel', $source);
        $this->assertStringContainsString('data-format="{{ $import->file_format }}"', $source);
        $this->assertStringContainsString('{{ $import->format_label }}', $source);
        $this->assertStringContainsString('format !== formatValue', $source);
        $this->assertStringNotContainsString('strtoupper($import->file_format)', $source);
    }
}
