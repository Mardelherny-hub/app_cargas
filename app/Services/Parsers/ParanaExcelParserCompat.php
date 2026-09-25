<?php

namespace App\Services\Parsers;

use App\Models\Vessel;
use App\Models\Voyage;

/**
 * Compatibilidad de Paraná con el número de viaje ingresado al importar.
 */
class ParanaExcelParserCompat extends ParanaExcelParser
{
    protected function createVoyage(
        array $data,
        array $options = []
    ): Voyage {
        $user = auth()->user();

        if (!$user) {
            throw new \Exception(
                'PARANA requiere un usuario autenticado.'
            );
        }

        $companyId = $user->company_id
            ?: (
                $user->userable_type === 'App\\Models\\Company'
                    ? $user->userable_id
                    : null
            );

        if (!$companyId) {
            throw new \Exception(
                "Usuario {$user->id} no tiene empresa asignada."
            );
        }

        $originPort = $this->resolvePortStrict(
            $this->requireParanaSourceText(
                $data['POL'] ?? null,
                'POL'
            )
        );

        $destPort = $this->resolvePortStrict(
            $this->requireParanaSourceText(
                $data['POD'] ?? null,
                'POD'
            )
        );

        $vesselId = $options['vessel_id'] ?? null;

        if (!$vesselId) {
            throw new \Exception(
                'PARANA requiere vessel_id seleccionado.'
            );
        }

        $vessel = Vessel::find($vesselId);

        if (!$vessel) {
            throw new \Exception(
                "Vessel con ID {$vesselId} no encontrado."
            );
        }

        if ((int) $vessel->company_id !== (int) $companyId) {
            throw new \Exception(
                'El vessel seleccionado no pertenece a la empresa importadora.'
            );
        }

        $voyageNumber = trim(
            (string) ($data['voyage_number'] ?? '')
        );

        if ($voyageNumber === '') {
            $voyageNumber = trim(
                (string) ($options['voyage_number'] ?? '')
            );
        }

        if ($voyageNumber === '') {
            throw new \Exception(
                'PARANA no informa VOYAGE_NO y no se ingresó un número de viaje.'
            );
        }

        $this->guardVoyageNumberIsFree($voyageNumber, (int) $vessel->id);

        $timing = $this->buildParanaVoyageTiming();

        return Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'lead_vessel_id' => $vessel->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destPort->country_id,
            'departure_date' => $timing['departure_date'],
            'estimated_arrival_date' =>
                $timing['estimated_arrival_date'],
            'status' => 'planning',
            'voyage_type' => $this->determineVoyageType($data),
            'cargo_type' => $this->determineCargoType(
                $data,
                $originPort,
                $destPort,
                (int) $companyId
            ),
            'created_by_user_id' => auth()->id(),
        ]);
    }
}
