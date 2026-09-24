<?php

namespace Tests\Unit\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class MicDtaClientPaginationTest extends TestCase
{
    private function containers(int $count): array
    {
        return collect(range(1, $count))->map(fn (int $i) => [
            'number' => sprintf('QA%08d', $i),
            'type' => '45G1',
            'seals' => sprintf('SEAL%05d AUX%05d', $i, $i),
        ])->all();
    }

    private function data(array $containers, string $description, string $billNumber): array
    {
        $party = [
            'company_name' => 'CLIENTE QA',
            'address' => 'DIRECCION QA',
            'tax_id' => '800000000',
            'phone' => '',
            'email' => '',
        ];

        return [
            'voyage' => [
                'voyage_number' => 'QA-PAGING',
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
                'bill_number' => $billNumber,
                'total_packages' => 200,
                'container_count' => count($containers),
                'volume_m3' => 500,
                'cargo_description' => $description,
                'gross_weight_kg' => 276400,
                'declared_value' => 0,
                'cargo_marks' => '',
                'containers' => $containers,
                'customs_remarks' => '',
            ]],
        ];
    }

    private function pageCount(array $data): int
    {
        $pdf = Pdf::loadView('company.reports.pdf.micdta-client', $data)
            ->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->getDomPDF()->getCanvas()->get_page_count();
    }

    public function test_fifty_containers_do_not_create_blank_pages(): void
    {
        $pages = $this->pageCount($this->data(
            $this->containers(50),
            'VACIO',
            '001PASU018626'
        ));

        $this->assertSame(
            2,
            $pages,
            'Un BL con 50 contenedores puede continuar en una segunda hoja, pero no debe generar paginas vacias intermedias.'
        );
    }

    public function test_long_cargo_description_does_not_force_blank_pages(): void
    {
        $description = trim(str_repeat(
            'BOVINE MEAT AND BONE MEAL NET WEIGHT 27190 KGS GROSS WEIGHT 27220 KGS TOTAL PALLETS 20 HS CODE 23011090 EXPORTER REFERENCE PB22744 SEAL SENACSA. ',
            28
        ));

        $this->assertGreaterThan(3500, mb_strlen($description));

        $pages = $this->pageCount($this->data(
            $this->containers(10),
            $description,
            '109TJSM35026'
        ));

        $this->assertLessThanOrEqual(
            3,
            $pages,
            'Una descripcion larga debe poder partirse entre hojas sin reservar filas imposibles ni insertar paginas vacias.'
        );
    }
}
