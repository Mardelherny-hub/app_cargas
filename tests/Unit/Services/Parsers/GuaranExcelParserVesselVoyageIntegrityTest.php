<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Port;
use App\Models\Vessel;
use App\Services\Parsers\GuaranExcelParser;
use ReflectionMethod;
use Tests\TestCase;

class GuaranExcelParserVesselVoyageIntegrityTest extends TestCase
{
    private GuaranExcelParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = app(GuaranExcelParser::class);
    }

    private function invoke(string $method, array $args = [])
    {
        $reflection = new ReflectionMethod(
            GuaranExcelParser::class,
            $method
        );
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $this->parser,
            $args
        );
    }

    public function test_extract_voyage_data_does_not_invent_agent_or_vessel_id(): void
    {
        $data = $this->invoke('extractVoyageData', [[
            'BARGE_NAME' => 'GUARAN F TEST',
            'VOYAGE_NO' => 'TEST-01',
            'BL_DATE' => '02/07/2025',
            'POL' => 'PYASU',
            'POD' => 'ARBUE',
        ]]);

        $this->assertNull($data['agent_name']);
        $this->assertNull($data['vessel_id']);
        $this->assertSame(
            'GUARAN F TEST',
            $data['vessel_name']
        );
    }

    public function test_vessel_creation_preserves_real_registration_without_inventing_specs(): void
    {
        $data = $this->invoke(
            'buildVesselCreationData',
            ['GUARAN F 503', '503', 1008]
        );

        $this->assertSame('GUARAN F 503', $data['name']);
        $this->assertSame('503', $data['registration_number']);
        $this->assertSame(1008, $data['company_id']);

        $this->assertNull($data['vessel_type_id']);
        $this->assertNull($data['flag_country_id']);
        $this->assertNull($data['length_meters']);
        $this->assertNull($data['beam_meters']);
        $this->assertNull($data['draft_meters']);
        $this->assertNull($data['gross_tonnage']);
        $this->assertNull($data['net_tonnage']);
        $this->assertNull($data['cargo_capacity_tons']);
    }

    public function test_vessel_creation_does_not_generate_registration_when_source_has_none(): void
    {
        $data = $this->invoke(
            'buildVesselCreationData',
            ['GUARAN F SIN MATRICULA', null, 1008]
        );

        $this->assertNull($data['registration_number']);
    }

    public function test_bl_date_is_not_repurposed_as_departure_or_eta(): void
    {
        $vessel = new Vessel();
        $vessel->setAttribute('id', 77);

        $origin = new Port();
        $origin->setAttribute('id', 10);
        $origin->setAttribute('country_id', 174);

        $destination = new Port();
        $destination->setAttribute('id', 20);
        $destination->setAttribute('country_id', 11);

        $data = $this->invoke(
            'buildVoyageCreationData',
            [[
                'voyage_number' => 'ABX 2525S',
                'manifest_type' => 'CM',
                'bl_date' => '02/07/2025',
                'agent_name' => null,
                'pol' => 'PYASU',
                'pod' => 'ARBUE',
            ], 1008, $vessel, $origin, $destination]
        );

        $this->assertSame('ABX 2525S', $data['voyage_number']);
        $this->assertSame(77, $data['lead_vessel_id']);
        $this->assertSame(10, $data['origin_port_id']);
        $this->assertSame(20, $data['destination_port_id']);
        $this->assertSame(174, $data['origin_country_id']);
        $this->assertSame(11, $data['destination_country_id']);

        $this->assertNull($data['departure_date']);
        $this->assertNull($data['estimated_arrival_date']);

        $this->assertSame(
            'Importado desde GUARAN Excel',
            $data['operational_notes']
        );
    }

    public function test_real_agent_name_is_preserved_only_when_source_provides_it(): void
    {
        $vessel = new Vessel();
        $vessel->setAttribute('id', 77);

        $origin = new Port();
        $origin->setAttribute('id', 10);
        $origin->setAttribute('country_id', 174);

        $destination = new Port();
        $destination->setAttribute('id', 20);
        $destination->setAttribute('country_id', 11);

        $data = $this->invoke(
            'buildVoyageCreationData',
            [[
                'voyage_number' => 'ABX 2525S',
                'manifest_type' => 'CM',
                'bl_date' => '02/07/2025',
                'agent_name' => 'AGENTE REAL',
                'pol' => 'PYASU',
                'pod' => 'ARBUE',
            ], 1008, $vessel, $origin, $destination]
        );

        $this->assertSame(
            'Importado desde GUARAN Excel: AGENTE REAL',
            $data['operational_notes']
        );
    }
}
