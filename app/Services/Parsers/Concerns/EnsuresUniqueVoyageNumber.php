<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Company;
use App\Models\Voyage;
use Exception;

/**
 * Garantiza que un número de viaje no se repita dentro de la misma empresa.
 *
 * La UI de creación manual ya aplica esta misma regla. Dos empresas distintas
 * pueden usar el mismo voyage_number sin colisionar entre sí.
 *
 * La clave 'voyages_voyage_number_unique' se conserva en el mensaje para que
 * los parsers puedan traducir de forma uniforme una colisión de unicidad.
 */
trait EnsuresUniqueVoyageNumber
{
    /**
     * Lanza una excepción controlada si el voyage_number ya existe en la
     * empresa del usuario autenticado.
     *
     * @param  string  $voyageNumber  Número de viaje YA calculado por el parser.
     * @throws Exception  Si el viaje ya existe en la misma empresa.
     */
    protected function guardVoyageNumberIsFree(string $voyageNumber): void
    {
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
                ->where('voyage_number', $voyageNumber)
                ->exists()
        ) {
            throw new Exception(
                "El viaje {$voyageNumber} ya existe en su empresa. "
                . 'voyages_voyage_number_unique'
            );
        }
    }
}
