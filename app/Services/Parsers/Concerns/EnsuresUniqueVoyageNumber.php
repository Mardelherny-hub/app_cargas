<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Voyage;
use Exception;

/**
 * Garantiza que un número de viaje no exista previamente en el sistema.
 *
 * Criterio del proyecto (aprobado por QA):
 * El voyage_number es ÚNICO GLOBAL. Si ya existe en CUALQUIER empresa,
 * la importación se bloquea ANTES de crear el viaje. No se reusa el viaje
 * ajeno ni se choca el índice único con un error SQL crudo.
 *
 * La clave 'voyages_voyage_number_unique' se embebe en el mensaje a propósito:
 * los catch de los parsers ya la detectan para traducirla al mensaje amable
 * que ve el usuario. Así el manejo de error queda uniforme en todos.
 */
trait EnsuresUniqueVoyageNumber
{
    /**
     * Lanza una excepción controlada si el voyage_number ya existe (global).
     *
     * @param  string  $voyageNumber  Número de viaje YA calculado por el parser.
     * @throws Exception  Si el viaje ya fue importado anteriormente.
     */
    protected function guardVoyageNumberIsFree(string $voyageNumber): void
    {
        if (Voyage::where('voyage_number', $voyageNumber)->exists()) {
            throw new Exception(
                "El viaje {$voyageNumber} ya fue importado anteriormente. "
                . "voyages_voyage_number_unique"
            );
        }
    }
}