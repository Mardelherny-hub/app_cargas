<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\MicDtaClientPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MicDtaClientPdfServiceTest extends TestCase
{
    private function data(): array
    {
        $fixture = new MicDtaClientPaginationTest('test_fifty_containers_do_not_create_blank_pages');
        return (new ReflectionMethod($fixture, 'data'))->invoke($fixture,
            [['number' => 'QA000000001', 'type' => '45G1', 'seals' => 'ABC123 XYZ456']],
            'MERCADERÍA QA', 'QA-BL-001');
    }

    private function temporaryDirectories(): array
    {
        return glob(sys_get_temp_dir().'/micdta-client-*') ?: [];
    }

    private function text(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mic-test-');
        try {
            file_put_contents($path, $bytes);
            $p = new Process(['/usr/bin/pdftotext', '-layout', $path, '-']);
            $p->mustRun();
            return $p->getOutput();
        } finally {
            unlink($path);
        }
    }

    public static function volumes(): array
    {
        return [[40, 67, 7], [300, 238, 24], [700, 476, 48]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('volumes')]
    public function test_full_volume_keeps_pages_numbering_and_detail(int $length, int $pages, int $batches): void
    {
        $before = $this->temporaryDirectories();
        $data = $this->data();
        $seed = $data['client_template_bills'][0];
        $data['client_template_bills'] = [];
        $itemCount = 0;
        for ($i = 0; $i < 52; $i++) {
            $bill = $seed;
            $bill['bill_number'] = sprintf('QA%08d', $i);
            $bill['items'] = [];
            for ($j = 0; $j < 2 + ($i < 15 ? 1 : 0); $j++) {
                $bill['items'][] = [
                    'quantity' => 20, 'description' => str_repeat('MERCADERIA QA ', (int) ceil($length / 14)),
                    'commodity_code' => '23011090', 'gross_weight_kg' => 27000,
                    'net_weight_kg' => 26000, 'volume_m3' => 30, 'declared_value' => 0,
                    'cargo_marks' => '', 'containers' => [[
                        'number' => sprintf('QA%09d', $itemCount), 'type' => '45G1', 'seals' => 'ABC123 XYZ456',
                        'package_quantity' => 20, 'gross_weight_kg' => 27000,
                        'net_weight_kg' => 26000, 'volume_m3' => 30,
                    ]],
                ];
                $itemCount++;
            }
            $data['client_template_bills'][] = $bill;
        }
        $service = new class extends MicDtaClientPdfService {
            public int $batches = 0;
            protected function renderBatch(string $html, string $path): void {
                $this->batches++;
                parent::renderBatch($html, $path);
            }
        };
        $bytes = $service->generate($data);
        $text = $this->text($bytes);
        $this->assertSame(119, $itemCount);
        $this->assertSame($batches, $service->batches);
        $this->assertSame($pages, substr_count($text, "\f"));
        preg_match_all('/Page:\s+(\d+)/', $text, $matches);
        $this->assertSame(range(1, $pages), array_map('intval', $matches[1]));
        $last = -1;
        for ($i = 0; $i < 52; $i++) {
            $position = strpos($text, sprintf('QA%08d', $i));
            $this->assertNotFalse($position);
            $this->assertGreaterThan($last, $position);
            $last = $position;
        }
        for ($i = 0; $i < 119; $i++) {
            $this->assertSame(1, substr_count($text, sprintf('QA%09d', $i)));
        }
        $this->assertSame(119, substr_count($text, 'ABC123 XYZ456'));
        $this->assertSame($pages, substr_count($text, 'MIC / DTA'));
        $this->assertSame($pages, substr_count($text, '17. Sello y firma del transportista'));
        $this->assertSame($before, $this->temporaryDirectories());
        fwrite(STDERR, json_encode(['description_chars' => mb_strlen($bill['items'][0]['description']),
            'pages' => $pages, 'batches' => $service->batches, 'peak_php' => memory_get_peak_usage(true),
            'pdf_bytes' => strlen($bytes), 'residuals' => count(array_diff($this->temporaryDirectories(), $before))])."\n");
    }

    public function test_small_report_matches_existing_pdf_and_cleans_all_temporaries(): void
    {
        $before = $this->temporaryDirectories();
        $data = $this->data();
        $expected = Pdf::loadView('company.reports.pdf.micdta-client', $data)
            ->setPaper('A4', 'portrait')->setOptions(['defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])->output();
        $actual = (new MicDtaClientPdfService())->generate($data);
        $this->assertStringStartsWith('%PDF-', $actual);
        $this->assertSame($this->text($expected), $this->text($actual));
        $this->assertSame($before, $this->temporaryDirectories());
    }

    public function test_missing_and_non_executable_binary_fail_without_temporaries(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not-executable-');
        chmod($path, 0600);
        try {
            foreach ([$path, $path.'-missing'] as $binary) {
                $before = $this->temporaryDirectories();
                $service = new class($binary) extends MicDtaClientPdfService {
                    public function __construct(private string $binary) {}
                    protected function pdfunitePath(): string { return $this->binary; }
                };
                try {
                    $service->generate($this->data());
                    $this->fail('Debe rechazar el motor no ejecutable');
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('motor de unión PDF no está disponible', $e->getMessage());
                }
                $this->assertSame($before, $this->temporaryDirectories());
            }
        } finally {
            unlink($path);
        }
    }

    public function test_render_failure_removes_partial_file_and_directory(): void
    {
        $before = $this->temporaryDirectories();
        $service = new class extends MicDtaClientPdfService {
            protected function renderBatch(string $html, string $path): void {
                file_put_contents($path, 'partial');
                throw new RuntimeException('render fallido');
            }
        };
        try {
            $service->generate($this->data());
            $this->fail('Debe propagar el fallo');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No se pudo generar el PDF MIC/DTA: render fallido', $e->getMessage());
        }
        $this->assertSame($before, $this->temporaryDirectories());
    }

    public function test_merge_process_failure_removes_all_files(): void
    {
        $before = $this->temporaryDirectories();
        $service = new class extends MicDtaClientPdfService {
            protected function renderBatch(string $html, string $path): void { file_put_contents($path, '%PDF-partial'); }
            protected function merge(string $binary, array $files, string $output): void {
                file_put_contents($output, 'partial');
                parent::merge('/usr/bin/false', $files, $output);
            }
        };
        try {
            $service->generate($this->data());
            $this->fail('Debe propagar el fallo');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Falló la unión', $e->getMessage());
        }
        $this->assertSame($before, $this->temporaryDirectories());
    }

    public function test_overlapping_generations_have_independent_directories(): void
    {
        $before = $this->temporaryDirectories();
        $data = $this->data();
        $inner = new class extends MicDtaClientPdfService {
            public array $paths = [];
            protected function renderBatch(string $html, string $path): void {
                $this->paths[] = $path; file_put_contents($path, '%PDF-test');
            }
            protected function merge(string $binary, array $files, string $output): void { copy($files[0], $output); }
        };
        $outer = new class($inner, $data) extends MicDtaClientPdfService {
            public array $paths = [];
            public function __construct(private $inner, private array $data) {}
            protected function renderBatch(string $html, string $path): void {
                $this->paths[] = $path; file_put_contents($path, '%PDF-test');
                $this->inner->generate($this->data);
                if (!is_file($path)) throw new RuntimeException('La otra solicitud eliminó el lote');
            }
            protected function merge(string $binary, array $files, string $output): void { copy($files[0], $output); }
        };
        $outer->generate($data);
        $this->assertNotSame(dirname($outer->paths[0]), dirname($inner->paths[0]));
        $this->assertSame($before, $this->temporaryDirectories());
    }
}
