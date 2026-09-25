<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Company;
use App\Models\Voyage;
use Exception;

/**
 * Garantiza que un número de viaje no se repita para la misma embarcación
 * dentro de una empresa.
 *
 * Dos empresas pueden usar el mismo voyage_number y una misma empresa puede
 * usarlo en barcos distintos. Sólo colisiona la terna:
 * company_id + lead_vessel_id + voyage_number.
 */
trait EnsuresUniqueVoyageNumber
{
    protected function guardVoyageNumberIsFree(
        string $voyageNumber,
        int $vesselId
    ): void {
        $user = auth()->user();

        $companyId = $user?->company_id
            ?? (
                $user?->userable_type === Company::class
                    ? (int) $user->userable_id
                    : null
            );

        if (!$companyId) {
            throw new Exception(
                'No se pudo determinar la empresa para validar el número de viaje.'
            );
        }

        if (
            Voyage::where('company_id', $companyId)
                ->where('lead_vessel_id', $vesselId)
                ->where('voyage_number', $voyageNumber)
                ->exists()
        ) {
            throw new Exception(
                "El viaje {$voyageNumber} ya existe para esta embarcación en su empresa. "
                . 'voyages_voyage_number_unique'
            );
        }
    }
}
