<?php

namespace App\Services\Parsers;

use App\Models\Vessel;
use App\Models\Voyage;
use App\ValueObjects\ManifestParseResult;

/**
 * Compatibilidad de Navsur con los valores operativos seleccionados al importar.
 */
class NavsurTextParserCompat extends NavsurTextParser
{
    protected array $importOptions = [];

    public function parse(
        string $filePath,
        array $options = []
    ): ManifestParseResult {
        $this->importOptions = $options;

        return parent::parse($filePath);
    }

    protected function findOrCreateVoyage(array $data): Voyage
    {
        $user = auth()->user();

        if (!$user) {
            throw new \DomainException(
                'Usuario no autenticado para importar Navsur.'
            );
        }

        $companyId = null;

        if (
            $user->userable_type === 'App\\Models\\Company'
            && $user->userable_id
        ) {
            $companyId = (int) $user->userable_id;
        } elseif (
            $user->userable_type === 'App\\Models\\Operator'
            && $user->userable
        ) {
            $companyId = (int) $user->userable->company_id;
        }

        if (!$companyId) {
            throw new \DomainException(
                "Usuario {$user->id} no tiene empresa asignada."
            );
        }

        $voyageNumber = trim(
            (string) ($data['voyage_number'] ?? '')
        );

        if ($voyageNumber === '') {
            $voyageNumber = trim(
                (string) ($this->importOptions['voyage_number'] ?? '')
            );
        }

        if ($voyageNumber === '') {
            throw new \DomainException(
                'Navsur no informa número de viaje y no se ingresó uno en la importación.'
            );
        }

        $this->guardVoyageNumberIsFree($voyageNumber);

        $originPort = $this->resolvePortStrict($data['pol']);
        $destinationPort = $this->resolvePortStrict($data['pod']);

        if (!$originPort->country_id) {
            throw new \DomainException(
                "El puerto {$data['pol']} no tiene país configurado."
            );
        }

        $selectedVesselId = $this->importOptions['vessel_id'] ?? null;

        if ($selectedVesselId) {
            $vessel = Vessel::where('id', $selectedVesselId)
                ->where('company_id', $companyId)
                ->where('active', true)
                ->where('operational_status', 'active')
                ->first();

            if (!$vessel) {
                throw new \DomainException(
                    'La embarcación seleccionada no pertenece a la empresa o no está activa.'
                );
            }
        } else {
            $flagCountryId = !empty($data['flag'])
                ? $this->mapFlagToCountryId($data['flag'])
                : null;

            $vessel = Vessel::where('company_id', $companyId)
                ->where('name', $data['vessel_name'])
                ->first();

            if (!$vessel) {
                $vessel = Vessel::create([
                    'name' => $data['vessel_name'],
                    'registration_number' => null,
                    'company_id' => $companyId,
                    'vessel_type_id' => null,
                    'flag_country_id' => $flagCountryId,
                    'length_meters' => null,
                    'beam_meters' => null,
                    'draft_meters' => null,
                    'cargo_capacity_tons' => null,
                    'container_capacity' => null,
                    'operational_status' => 'active',
                    'active' => true,
                ]);
            } elseif (
                !$vessel->flag_country_id
                && $flagCountryId
            ) {
                $vessel->update([
                    'flag_country_id' => $flagCountryId,
                ]);
            }
        }

        return Voyage::create([
            'voyage_number' => $voyageNumber,
            'company_id' => $companyId,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destinationPort->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' =>
                $destinationPort->country_id ?: null,
            'voyage_type' => 'single_vessel',
            'cargo_type' => $this->resolveVoyageCargoTypeForCompany(
                $companyId,
                $originPort,
                $destinationPort
            ),
            'status' => 'planning',
            'departure_date' => null,
            'estimated_arrival_date' => null,
            'total_cargo_capacity_tons' => 0,
            'total_container_capacity' => 0,
            'total_cargo_weight_loaded' => 0,
            'total_containers_loaded' => 0,
            'capacity_utilization_percentage' => 0,
        ]);
    }
}
