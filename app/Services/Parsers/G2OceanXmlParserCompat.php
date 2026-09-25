<?php

namespace App\Services\Parsers;

use App\Models\Vessel;
use App\Models\Voyage;
use Exception;

/**
 * Compatibilidad de G2Ocean con los datos operativos ingresados al importar.
 */
class G2OceanXmlParserCompat extends G2OceanXmlParser
{
    protected function createVoyage(
        array $blData,
        array $options
    ): Voyage {
        $user = auth()->user();

        if (!$user) {
            throw new Exception(
                'G2Ocean requiere usuario autenticado.'
            );
        }

        $companyId = $user->company_id
            ?: (
                $user->userable_type === 'App\\Models\\Company'
                    ? $user->userable_id
                    : null
            );

        if (!$companyId) {
            throw new Exception(
                'Usuario no tiene empresa asignada.'
            );
        }

        $vessel = Vessel::find(
            $options['vessel_id'] ?? null
        );

        if (!$vessel) {
            throw new Exception(
                'Vessel seleccionado no encontrado.'
            );
        }

        if ((int) $vessel->company_id !== (int) $companyId) {
            throw new Exception(
                'El vessel seleccionado no pertenece a la empresa importadora.'
            );
        }

        $originPort = $this->findOrCreatePort(
            $this->requireG2OceanText(
                $blData['loading_port_code'] ?? null,
                'portOfLoading'
            )
        );

        $destinationPort = $this->findOrCreatePort(
            $this->requireG2OceanText(
                $blData['discharge_port_code'] ?? null,
                'portOfDischarge'
            )
        );

        $voyageNumber = trim(
            (string) ($blData['voyage_number'] ?? '')
        );

        if ($voyageNumber === '') {
            $voyageNumber = trim(
                (string) ($options['voyage_number'] ?? '')
            );
        }

        if ($voyageNumber === '') {
            throw new Exception(
                'G2Ocean no informa voyageNo y no se ingresó un número de viaje.'
            );
        }

        $this->guardVoyageNumberIsFree($voyageNumber, (int) $vessel->id);

        $voyage = Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'origin_port_id' => $originPort->id,
            'destination_port_id' =>
                $destinationPort->id,
            'lead_vessel_id' => $vessel->id,
            'origin_country_id' =>
                $originPort->country_id,
            'destination_country_id' =>
                $destinationPort->country_id,
            'departure_date' => null,
            'estimated_arrival_date' => null,
            'voyage_type' => 'single_vessel',
            'cargo_type' => $this->resolveVoyageCargoTypeForCompany(
                (int) $companyId,
                $originPort,
                $destinationPort
            ),
            'status' => 'planning',
            'created_by_user_id' => $user->id,
        ]);

        $this->stats['created_voyages']++;

        return $voyage;
    }
}
