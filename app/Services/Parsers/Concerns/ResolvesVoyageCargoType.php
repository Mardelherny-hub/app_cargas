<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Company;
use App\Models\Country;
use App\Models\Port;
use DomainException;

trait ResolvesVoyageCargoType
{
    protected function resolveVoyageCargoTypeForCompany(
        int $companyId,
        Port $originPort,
        Port $destinationPort
    ): string {
        $homeCountryCode = strtoupper(trim((string) Company::query()
            ->whereKey($companyId)
            ->value('country')));

        $originCountryCode = strtoupper(trim((string) Country::query()
            ->whereKey($originPort->country_id)
            ->value('alpha2_code')));

        $destinationCountryCode = strtoupper(trim((string) Country::query()
            ->whereKey($destinationPort->country_id)
            ->value('alpha2_code')));

        return $this->resolveVoyageCargoTypeCodes(
            $homeCountryCode,
            $originCountryCode,
            $destinationCountryCode
        );
    }

    protected function resolveVoyageCargoTypeCodes(
        string $homeCountryCode,
        string $originCountryCode,
        string $destinationCountryCode
    ): string {
        $home = strtoupper(trim($homeCountryCode));
        $origin = strtoupper(trim($originCountryCode));
        $destination = strtoupper(trim($destinationCountryCode));

        if ($home === '') {
            throw new DomainException(
                'La empresa importadora no tiene país configurado.'
            );
        }

        if ($origin === '' || $destination === '') {
            throw new DomainException(
                'No se puede determinar la operación: país de puerto no resuelto.'
            );
        }

        if ($origin === $destination) {
            return 'cabotage';
        }

        if ($origin === $home) {
            return 'export';
        }

        if ($destination === $home) {
            return 'import';
        }

        return 'transit';
    }
}
