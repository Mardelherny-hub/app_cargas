<?php

namespace Tests\Unit\Reports;

use App\Http\Controllers\Company\ReportController;
use App\Models\Company;
use App\Services\Reports\ManifestReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Mockery;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ManifestPresentationFlowTest extends TestCase
{
    private function renderScreen(bool $loaded): string
    {
        $this->app->instance('request', Request::create('/company/reports/manifests',
            'GET', $loaded ? ['client_voyage_id' => '7'] : []));
        $voyage = (object) ['id' => 7, 'voyage_number' => 'V-7', 'leadVessel' => null,
            'originPort' => null, 'destinationPort' => null, 'departure_date' => null,
            'bills_of_lading_count' => 2];
        $bills = collect([11, 12])->map(fn ($id) => (object) [
            'id' => $id, 'bill_number' => 'BL-' . $id, 'loadingPort' => null,
            'dischargePort' => null, 'finalDestinationPort' => null,
        ]);
        // Renderizar el contenido real sin el layout global (navegación autenticada).
        $source = file_get_contents(resource_path('views/company/reports/manifests.blade.php'));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>',
            '<x-slot name="header">', '</x-slot>'], '', $source);
        return Blade::render($source, [
            'reportVoyages' => collect([$voyage]),
            'clientManifestVoyage' => $loaded ? $voyage : null,
            'clientManifestBills' => $loaded ? $bills : collect(),
        ]);
    }

    public function test_render_exposes_both_choices_and_preserves_loaded_voyage_and_bills(): void
    {
        foreach ([false, true] as $loaded) {
            $html = $this->renderScreen($loaded);
            $this->assertStringNotContainsString('formato según muestra', $html);
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame('Formato actual', trim($xpath->evaluate('string(//select[@id="manifest-presentation"]/option[@value="standard"])')));
            $this->assertSame('Cargo Manifest', trim($xpath->evaluate('string(//select[@id="manifest-presentation"]/option[@value="client"])')));
            $this->assertSame(1, $xpath->query('//select[@name="filters[voyage_id]"]')->length);
            $this->assertSame(0, $xpath->query('//select[@name="client_voyage_id"]')->length);
            $this->assertSame('POST', $xpath->evaluate('string(//form[@id="manifest-report-form"]/@method)'));
            if ($loaded) {
                $this->assertSame('7', $xpath->evaluate('string(//select[@id="voyage_id"]/option[@selected]/@value)'));
                $this->assertSame('client', $xpath->evaluate('string(//select[@id="manifest-presentation"]/option[@selected]/@value)'));
                $this->assertSame(2, $xpath->query('//input[@name="filters[bill_ids][]"][@checked]')->length);
                $this->assertSame('client', $xpath->evaluate('string(//form[@id="manifest-client-form"]/input[@name="filters[template]"]/@value)'));
                $this->assertSame('7', $xpath->evaluate('string(//form[@id="manifest-client-form"]/input[@name="filters[voyage_id]"]/@value)'));
            }
        }
    }

    public function test_actual_javascript_routes_same_voyage_and_keeps_bill_selection_guard(): void
    {
        $html = $this->renderScreen(true);
        preg_match_all('/<script>(.*?)<\/script>/s', $html, $scripts);
        $harness = <<<'JS'
const assert = require('node:assert/strict');
const elements = {};
function element(id) {
    return elements[id] ??= {value: '', dataset: {}, checked: true, handlers: {},
        classList: {add(){}, remove(){}},
        addEventListener(name, fn) { this.handlers[name] = fn; }};
}
global.document = {getElementById: element};
global.window = {location: {href: 'https://example.test/company/reports/manifests',
    assign(url) { this.assigned = url; }}};
global.alert = value => { global.lastAlert = value; };
const bills = [element('bill1'), element('bill2')];
element('manifest-client-form').querySelectorAll = () => bills;
const form = element('manifest-report-form');
form.dataset.loadUrl = '/company/reports/manifests';
const presentation = element('manifest-presentation');
presentation.value = 'client';
const voyage = element('voyage_id');
voyage.value = '7';
voyage.selectedIndex = 0;
voyage.options = [{value: '7', dataset: {}}];
element('manifest-cargo-options').dataset.voyageId = '7';
JS;
        $harness .= "\n" . implode("\n", $scripts[1]) . "\n";
        $harness .= <<<'JS'
assert.equal(element('manifest-cargo-options').hidden, false);
assert.equal(element('format').disabled, true);
let prevented = false;
form.handlers.submit({preventDefault(){ prevented = true; }});
assert.equal(prevented, true);
assert.equal(new URL(window.location.assigned).searchParams.get('client_voyage_id'), '7');
presentation.value = 'standard';
presentation.handlers.change();
assert.equal(voyage.value, '7');
assert.equal(element('format').disabled, false);
assert.equal(element('status_filter').disabled, false);
assert.equal(element('manifest-cargo-options').hidden, true);
prevented = false;
form.handlers.submit({preventDefault(){ prevented = true; }});
assert.equal(prevented, false);
presentation.value = 'client';
presentation.handlers.change();
assert.equal(element('manifest-cargo-options').hidden, false);
voyage.value = '8';
voyage.handlers.change();
assert.equal(element('manifest-cargo-options').hidden, true);
form.handlers.submit({preventDefault(){}});
assert.equal(new URL(window.location.assigned).searchParams.get('client_voyage_id'), '8');
const all = element('manifest-select-all');
all.checked = false;
all.handlers.change();
assert.equal(bills.some(b => b.checked), false);
prevented = false;
element('manifest-client-form').handlers.submit({preventDefault(){ prevented = true; }});
assert.equal(prevented, true);
bills[0].checked = true;
bills[0].handlers.change();
assert.equal(all.checked, false);
prevented = false;
element('manifest-client-form').handlers.submit({preventDefault(){ prevented = true; }});
assert.equal(prevented, false);
process.stdout.write('PASS');
JS;
        $process = new Process(['node', '-e', $harness]);
        $process->mustRun();
        $this->assertSame('PASS', $process->getOutput());
    }

    public function test_both_outputs_still_use_the_existing_pdf_templates(): void
    {
        foreach (['standard' => 'manifest', 'client' => 'manifest-client'] as $format => $view) {
            $service = Mockery::mock(ManifestReportService::class);
            $service->shouldReceive('getFilters')->once()->andReturn(['template' => $format]);
            $service->shouldReceive('getSuggestedFilename')->with('pdf')->once()->andReturn('report.pdf');
            $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            Pdf::shouldReceive('loadView')->once()->with('company.reports.pdf.' . $view, ['company_logo' => null])->andReturn($pdf);
            $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
            $pdf->shouldReceive('download')->once()->with('report.pdf')->andReturn(response('pdf-result'));
            if ($format === 'client') {
                $pdf->shouldReceive('render')->once()->andReturnSelf();
                $pdf->shouldReceive('getDomPDF')->once()->andReturn(new \Dompdf\Dompdf());
            }
            $result = (new ReflectionMethod(ReportController::class, 'generateManifestPDF'))
                ->invoke(new ReportController(), [], $service, new Company());
            $this->assertSame('pdf-result', $result->getContent());
        }
    }
}
