<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Company;
use App\Models\DeconsolidationOperation;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

class ArgentinaDeconsolidatedEmbarkationDateTest extends TestCase
{
    private function generator(): SimpleXmlGeneratorDesconsolidado
    {
        $company = new Company();
        $company->ws_environment = 'testing';
        $operation = new DeconsolidationOperation();
        $operation->arrival_date = '2026-10-20';
        $operation->setRelation('company', $company);

        return new SimpleXmlGeneratorDesconsolidado($operation, [
            'environment' => 'testing',
        ]);
    }

    #[Test]
    public function missing_embarkation_date_is_rejected_before_customs_xml(): void
    {
        $bill = new BillOfLading();
        $bill->bill_number = 'CHILD-TEST';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Conocimiento CHILD-TEST: falta la fecha de embarque.'
        );
        (new ReflectionMethod($this->generator(), 'validateBill'))
            ->invoke($this->generator(), $bill);
    }

    #[Test]
    public function valid_embarkation_date_uses_existing_bill_field_and_xml_element(): void
    {
        $bill = new BillOfLading();
        $bill->loading_date = '2026-10-19';

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('TituloDesconsolidador');

        (new ReflectionMethod($this->generator(), 'writeOptionalDate'))
            ->invoke($this->generator(), $writer, 'FechaEmbarque', $bill->loading_date);

        $writer->endElement();
        $writer->endDocument();
        $xml = $writer->outputMemory();

        $this->assertStringContainsString(
            '<FechaEmbarque>2026-10-19T00:00:00</FechaEmbarque>',
            $xml
        );
        $this->assertStringNotContainsString('2026-10-20', $xml);
    }

    #[Test]
    public function house_bill_form_and_controller_keep_embarkation_editable_in_drafts(): void
    {
        $view = file_get_contents(resource_path(
            'views/company/simple/desconsolidado/operations/bills/create.blade.php'
        ));
        $controller = file_get_contents(app_path(
            'Http/Controllers/Company/Simple/DeconsolidationBillController.php'
        ));

        $this->assertStringContainsString('name="loading_date"', $view);
        $this->assertStringContainsString(
            'old(\'loading_date\', $editingBill?->loading_date?->format(\'Y-m-d\'))',
            $view
        );
        $this->assertSame(
            2,
            substr_count($controller, "'loading_date' => ['nullable', 'date_format:Y-m-d']")
        );
        $this->assertStringContainsString(
            "'loading_date' => \$data['loading_date'] ?? null",
            $controller
        );
        $this->assertStringContainsString(
            "'bill_number', 'loading_date', 'shipper_id'",
            $controller
        );
    }

    #[Test]
    public function transmission_button_is_visible_and_has_direct_jump_link(): void
    {
        $view = file_get_contents(resource_path(
            'views/company/simple/desconsolidado/operations/show.blade.php'
        ));

        $this->assertStringContainsString('href="#ata-desc-transmit"', $view);
        $this->assertStringContainsString('id="ata-desc-transmit"', $view);
        $this->assertStringContainsString('>Enviar a Aduana</button>', $view);
        $this->assertStringContainsString(
            'bg-indigo-600 px-4 py-2 text-sm font-bold text-white',
            $view
        );
        $this->assertStringNotContainsString('bg-amber-700', $view);
    }
}
