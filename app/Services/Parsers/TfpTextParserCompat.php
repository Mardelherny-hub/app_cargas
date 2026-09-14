<?php

namespace App\Services\Parsers;

use App\Models\Vessel;
use App\Models\Voyage;
use Exception;

/**
 * Compatibilidad TFP para respetar los datos fuente del viaje y mantener la
 * embarcación seleccionada por el operador como elección autoritativa.
 */
class TfpTextParserCompat extends TfpTextParser
{
    protected function findOrCreateVoyage(
        array $data,
        array $options = []
    ): Voyage {
        $user = auth()->user();

        if (!$user) {
            throw new Exception('TFP requiere usuario autenticado.');
        }

        $companyId = null;

        if ($user->userable_type === 'App\\Models\\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } elseif ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
            $companyId = (int) $user->userable->company_id;
        }

        if (!$companyId) {
            throw new Exception('Usuario no tiene empresa asignada.');
        }

        $vessel = Vessel::find($options['vessel_id'] ?? null);

        if (!$vessel) {
            throw new Exception('TFP: vessel_id es obligatorio.');
        }

        if ((int) $vessel->company_id !== $companyId) {
            throw new Exception(
                'El vessel seleccionado no pertenece a la empresa importadora.'
            );
        }

        $originPort = $this->findOrCreatePort($data['pol']);
        $destPort = $this->findOrCreatePort($data['pod']);

        $voyageNumber = trim(
            (string) ($data['voyage_number'] ?? '')
        );

        if ($voyageNumber === '') {
            $voyageNumber = trim(
                (string) ($options['voyage_number'] ?? '')
            );
        }

        if ($voyageNumber === '') {
            throw new Exception(
                'TFP no informa número de viaje y no se ingresó uno en la importación.'
            );
        }

        $this->guardVoyageNumberIsFree($voyageNumber);

        return Voyage::create([
            'voyage_number' => $voyageNumber,
            'company_id' => $companyId,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destPort->country_id,
            'status' => 'planning',
            'voyage_type' => 'single_vessel',
            'cargo_type' => $this->resolveTfpVoyageCargoType(
                $originPort,
                $destPort
            ),
            'departure_date' => null,
            'estimated_arrival_date' => null,
            'total_cargo_capacity_tons' => $vessel->cargo_capacity_tons,
            'total_container_capacity' => $vessel->container_capacity ?? 0,
            'total_cargo_weight_loaded' => 0,
            'total_containers_loaded' => 0,
            'capacity_utilization_percentage' => 0,
        ]);
    }
}
