<?php

namespace App\Services\Reports;

use DOMDocument;
use DOMXPath;
use Dompdf\Dompdf;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class MicDtaClientPdfService
{
    public function generate(array $data): string
    {
        $directory = null;
        try {
            $binary = $this->pdfunitePath();
            if (!is_file($binary) || !is_executable($binary)) {
                throw new RuntimeException('El motor de unión PDF no está disponible.');
            }

            // La plantilla conserva selección, orden, CSS y numeración global.
            $html = view('company.reports.pdf.micdta-client', $data)->render();
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                if (!$document->loadHTML('<?xml encoding="UTF-8">'.$html)) {
                    throw new RuntimeException('No se pudo interpretar el documento.');
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $head = $document->saveHTML($document->getElementsByTagName('head')->item(0));
            $pages = [];
            foreach ((new DOMXPath($document))->query('//div[@class="page"]') as $page) {
                $pages[] = $document->saveHTML($page);
            }
            if ($pages === []) {
                $pages[] = $document->saveHTML($document->getElementsByTagName('body')->item(0));
            }
            unset($document, $html, $data);

            $candidate = sys_get_temp_dir().'/micdta-client-'.bin2hex(random_bytes(16));
            if (!mkdir($candidate, 0700)) {
                throw new RuntimeException('No se pudo crear el directorio temporal.');
            }
            $directory = $candidate;
            $files = [];
            foreach (array_chunk($pages, 10) as $index => $batch) {
                $path = $directory.'/batch-'.sprintf('%05d', $index).'.pdf';
                $this->renderBatch(
                    '<!DOCTYPE html><html lang="es">'.$head.'<body>'.implode('', $batch).'</body></html>',
                    $path
                );
                $files[] = $path;
            }
            $output = $directory.'/result.pdf';
            $this->merge($binary, $files, $output);
            $contents = file_get_contents($output);
            if ($contents === false || !str_starts_with($contents, '%PDF-')) {
                throw new RuntimeException('El motor de unión no produjo un PDF válido.');
            }
            return $contents;
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo generar el PDF MIC/DTA: '.$e->getMessage(), 0, $e);
        } finally {
            if ($directory !== null && is_dir($directory)) {
                foreach (glob($directory.'/*') ?: [] as $file) {
                    unlink($file);
                }
                rmdir($directory);
            }
        }
    }

    protected function pdfunitePath(): string
    {
        return '/usr/bin/pdfunite';
    }

    protected function renderBatch(string $html, string $path): void
    {
        $pdf = new Dompdf(config('dompdf.options', []));
        try {
            $pdf->getOptions()->set([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);
            $pdf->setPaper('A4', 'portrait');
            $pdf->loadHtml($html);
            $pdf->render();
            if (file_put_contents($path, $pdf->output()) === false) {
                throw new RuntimeException('No se pudo escribir el lote PDF.');
            }
        } finally {
            unset($pdf);
            gc_collect_cycles();
        }
    }

    protected function merge(string $binary, array $files, string $output): void
    {
        $process = new Process([$binary, ...$files, $output]);
        $process->setTimeout(120);
        if ($process->run() !== 0) {
            throw new RuntimeException('Falló la unión de los lotes PDF.');
        }
    }
}
