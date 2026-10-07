<?php

namespace App\Services\Simple;

use App\Models\Company;
use InvalidArgumentException;

/** Ambiente de Aduana de los circuitos Simple: exclusivamente el switch de empresa. */
final class WebserviceEnvironment
{
    public static function resolve(Company $company): string
    {
        $environment = $company->ws_environment;
        if (!in_array($environment, ['testing', 'production'], true)) {
            throw new InvalidArgumentException(
                'La empresa no tiene un ambiente de Aduana válido configurado (testing/production).'
            );
        }

        return $environment;
    }

    public static function argentinaEndpoint(Company $company, string $service): string
    {
        $environment = self::resolve($company);
        $host = [
            'testing' => 'https://wsaduhomoext.afip.gob.ar',
            'production' => 'https://webservicesadu.afip.gob.ar',
        ][$environment];

        return "{$host}/DIAV2/{$service}/{$service}.asmx";
    }
}
