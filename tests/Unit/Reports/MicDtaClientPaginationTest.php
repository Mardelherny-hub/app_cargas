<?php

namespace Tests\Unit\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class MicDtaClientPaginationTest extends TestCase
{
    public function test_fifty_containers_do_not_create_blank_pages(): void
    {
        $containers = collect(range(1, 50))->map(fn (int $i) => [
            'number' => sprintf('QA%08d', $i),
            'type' => '45G1',
            'seals' => sprintf('SEAL%05d AUX%05d', $i, $i),
        ])->all();

        $party = [
            'company_name' => 'CLIENTE QA',
            'address' => 'DIRECCION QA',
            'tax_id' => '800000000',
            'phone' => '',
            'email' => '',
        ];

        $data = [
            'voyage' => [
                'voyage_number' => 'QA-50',
                'vessel_name' => 'ASUNCION B',
                'vessel_registration' => 'ASUNCION B',
            ],
            'company' => [
                'legal_name' => 'Nabsa SA',
                'address' => 'Paseo Colon 728',
                'city' => 'CABA',
                'tax_id' => '30612732503',
            ],
            'micdta' => [
                'number' => '',
                'date' => '23/09/2026',
                'customs_observation' => '',
            ],
            'client_template_bills' => [[
                'is_transit' => false,
                'mic_dta_number' => '',
                'bill_date' => '23/09/2026',
                'shipper' => $party,
                'consignee' => $party,
                'notify' => $party,
                'loading_port' => 'Puerto Seguro Fluvial',
                'final_destination_port' => 'Buenos Aires',
                'bill_number' => '001PASU018626',
                'total_packages' => 0,
                'container_count' => 50,
                'volume_m3' => 0,
                'cargo_description' => 'VACIO',
                'gross_weight_kg' => 0,
                'declared_value' => 0,
                'cargo_marks' => '',
                'containers' => $containers,
                'customs_remarks' => '',
            ]],
        ];

        $pdf = Pdf::loadView('company.reports.pdf.micdta-client', $data)
            ->setPaper('A4', 'portrait');
        $pdf->render();

        $this->assertSame(
            2,
            $pdf->getDomPDF()->getCanvas()->get_page_count(),
            'Un BL con 50 contenedores puede continuar en una segunda hoja, pero no debe generar paginas vacias intermedias.'
        );
    }
}
