<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Client;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Identificación única de clientes existentes para los parsers.
 *
 * REGLA 1 - Con tax_id: busca por tax_id en todos los países.
 *   1 coincidencia -> se reutiliza tal cual (si el país difiere, advertencia;
 *   la ficha no se modifica). Varias -> la del mismo país; si ninguna
 *   coincide -> excepción.
 * REGLA 2 - Sin tax_id: busca por nombre normalizado (legal_name o
 *   commercial_name) en todos los países. 1 -> se reutiliza (si el país
 *   difiere, advertencia); varias -> la del mismo país; si sigue ambiguo ->
 *   excepción.
 * REGLA 3 - Con tax_id sin coincidencia fiscal: busca fichas SIN tax_id con
 *   el mismo nombre normalizado. Varias -> mismo país; ambiguo -> excepción.
 *   Si la ficha es del mismo país, se le completan tax_id y document_type_id
 *   en vez de crear. Si es de otro país, se reutiliza tal cual con
 *   advertencia, sin escribir nada.
 *
 * Sin coincidencias devuelve client = null y el parser crea como hoy.
 * No filtra por status: también se reutilizan clientes inactivos/suspendidos.
 *
 * El trait no escribe advertencias: las devuelve y cada parser las agrega a
 * su acumulador ($this->warnings o $this->stats['warnings']).
 *
 * Rendimiento: el índice de nombres se arma una sola vez por instancia del
 * parser (una consulta, sin hidratar modelos). Después de crear un cliente,
 * el parser debe llamar a rememberClientIdentity() para que el resto del
 * archivo lo encuentre y no se dupliquen partes repetidas.
 */
trait ResolvesExistingClient
{
    /**
     * Nombre normalizado -> [client_id => [country_id, has_tax_id]].
     *
     * @var array<string, array<int, array{country_id: int, has_tax_id: bool}>>|null
     */
    private ?array $clientIdentityIndex = null;

    /**
     * @return array{client: ?Client, warnings: array<int, string>}
     *
     * @throws DomainException si la coincidencia es ambigua.
     */
    protected function resolveExistingClient(
        ?string $taxId,
        string $name,
        int $countryId,
        ?int $documentTypeId = null
    ): array {
        $taxId = $this->normalizeClientTaxId($taxId);
        $nameKey = $this->normalizeClientIdentityName($name);

        // REGLA 2: sin identificador fiscal, solo por nombre.
        if ($taxId === '') {
            $clientId = $this->pickClientByIdentityName($nameKey, $name, $countryId, false);

            if ($clientId === null) {
                return ['client' => null, 'warnings' => []];
            }

            $client = Client::find($clientId);
            $warnings = [];

            if ((int) $client->country_id !== $countryId) {
                $warnings[] = "Cliente {$name}: se reutilizó el cliente #{$client->id}, "
                    . 'registrado con otro país. La ficha no se modificó.';
            }

            return ['client' => $client, 'warnings' => $warnings];
        }

        // REGLA 1: por tax_id, sin filtrar país.
        $taxMatches = Client::query()
            ->toBase()
            ->where('tax_id', $taxId)
            ->get(['id', 'country_id']);

        if ($taxMatches->count() === 1) {
            $match = $taxMatches->first();
            $warnings = [];

            $client = Client::find($match->id);

            if ((int) $match->country_id !== $countryId) {
                $warnings[] = "Identificación fiscal {$taxId} ({$name}): se reutilizó "
                    . "el cliente #{$match->id}, registrado con otro país. "
                    . 'La ficha no se modificó.';
            } else {
                $this->completeMissingDocumentType($client, $documentTypeId);
            }

            return ['client' => $client, 'warnings' => $warnings];
        }

        if ($taxMatches->count() > 1) {
            $sameCountry = $taxMatches->first(
                static fn ($row) => (int) $row->country_id === $countryId
            );

            if (!$sameCountry) {
                throw new DomainException(
                    "Identificación fiscal {$taxId} ({$name}) corresponde a "
                    . 'múltiples clientes y ninguno coincide con el país del archivo. '
                    . 'Debe resolverse la duplicidad antes de importar.'
                );
            }

            $client = Client::find($sameCountry->id);
            $this->completeMissingDocumentType($client, $documentTypeId);

            return ['client' => $client, 'warnings' => []];
        }

        // REGLA 3: ficha sin tax_id con el mismo nombre.
        $clientId = $this->pickClientByIdentityName($nameKey, $name, $countryId, true);

        if ($clientId === null) {
            return ['client' => null, 'warnings' => []];
        }

        $client = Client::find($clientId);

        // Otro país: se reutiliza tal cual, sin completar la ficha.
        if ((int) $client->country_id !== $countryId) {
            return [
                'client' => $client,
                'warnings' => [
                    "Identificación fiscal {$taxId} ({$name}): se reutilizó el cliente "
                    . "#{$client->id} sin identificación fiscal, registrado con otro país. "
                    . 'La ficha no se modificó.',
                ],
            ];
        }

        // Clave única (tax_id, country_id): no escribir si ya está tomada.
        $taken = Client::query()
            ->toBase()
            ->where('tax_id', $taxId)
            ->where('country_id', $client->country_id)
            ->exists();

        if ($taken) {
            throw new DomainException(
                "Identificación fiscal {$taxId} ({$name}) ya está registrada para "
                . "el país de la ficha #{$client->id}. Debe resolverse antes de importar."
            );
        }

        $client->updateQuietly([
            'tax_id' => $taxId,
            'document_type_id' => $documentTypeId ?? $client->document_type_id,
        ]);

        $this->rememberClientIdentity($client);

        Log::info('Ficha de cliente sin identificación fiscal completada desde importación', [
            'client_id' => $client->id,
            'tax_id' => $taxId,
            'document_type_id' => $client->document_type_id,
        ]);

        return [
            'client' => $client,
            'warnings' => [
                "Cliente #{$client->id} ({$name}) no tenía identificación fiscal; "
                . "se completó con {$taxId}.",
            ],
        ];
    }

    /**
     * Completa document_type_id solo si la ficha no lo tiene. Nunca pisa uno
     * existente. Se usa únicamente para coincidencias del mismo país.
     */
    private function completeMissingDocumentType(Client $client, ?int $documentTypeId): void
    {
        if ($documentTypeId === null || $client->document_type_id !== null) {
            return;
        }

        $client->updateQuietly(['document_type_id' => $documentTypeId]);
    }

    /**
     * Registra (o actualiza) en el índice un cliente creado o completado
     * durante la importación, bajo todas sus claves de nombre, para que las
     * partes siguientes del archivo lo reutilicen.
     */
    protected function rememberClientIdentity(Client $client): void
    {
        if ($this->clientIdentityIndex === null) {
            return; // El índice todavía no se armó: lo leerá de la base.
        }

        $this->indexClientIdentity(
            (int) $client->id,
            (int) $client->country_id,
            $client->tax_id !== null && $client->tax_id !== '',
            $client->legal_name,
            $client->commercial_name
        );
    }

    /**
     * Identidad nominal estable: mayúsculas y solo letras/números Unicode.
     * Mismo criterio que CmspEdiParser, TfpTextParser y G2OceanXmlParser.
     */
    protected function normalizeClientIdentityName(string $name): string
    {
        $normalized = mb_strtoupper(trim($name), 'UTF-8');

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '';
    }

    /**
     * Quita solo separadores (espacios, puntos, guiones, barras) y pasa a
     * mayúsculas. Conserva letras y números.
     */
    protected function normalizeClientTaxId(?string $taxId): string
    {
        $normalized = mb_strtoupper((string) $taxId, 'UTF-8');

        return preg_replace('/[\s.\-\/]+/u', '', $normalized) ?? '';
    }

    /**
     * Elige la ficha por nombre normalizado: única -> esa; varias -> la única
     * del mismo país; si sigue ambiguo -> excepción. null si no hay ninguna.
     */
    private function pickClientByIdentityName(
        string $nameKey,
        string $name,
        int $countryId,
        bool $onlyWithoutTaxId
    ): ?int {
        if ($nameKey === '') {
            return null;
        }

        $candidates = $this->clientIdentityIndex()[$nameKey] ?? [];

        if ($onlyWithoutTaxId) {
            $candidates = array_filter($candidates, static fn (array $c) => !$c['has_tax_id']);
        }

        if (count($candidates) === 0) {
            return null;
        }

        if (count($candidates) === 1) {
            return array_key_first($candidates);
        }

        $sameCountry = array_filter(
            $candidates,
            static fn (array $c) => $c['country_id'] === $countryId
        );

        if (count($sameCountry) === 1) {
            return array_key_first($sameCountry);
        }

        throw new DomainException(
            "Cliente {$name}: existen " . count($candidates) . ' fichas con el mismo '
            . 'nombre (#' . implode(', #', array_keys($candidates)) . ') y no se puede '
            . 'determinar cuál corresponde. Debe resolverse la duplicidad antes de importar.'
        );
    }

    /**
     * Arma el índice una sola vez: una consulta en bloques, sin hidratar
     * modelos ni cargar primaryContact ($with del modelo).
     */
    private function clientIdentityIndex(): array
    {
        if ($this->clientIdentityIndex !== null) {
            return $this->clientIdentityIndex;
        }

        $this->clientIdentityIndex = [];

        Client::query()
            ->toBase()
            ->select(['id', 'tax_id', 'country_id', 'legal_name', 'commercial_name'])
            ->orderBy('id')
            ->lazy(1000)
            ->each(function ($row) {
                $this->indexClientIdentity(
                    (int) $row->id,
                    (int) $row->country_id,
                    $row->tax_id !== null && $row->tax_id !== '',
                    $row->legal_name,
                    $row->commercial_name
                );
            });

        return $this->clientIdentityIndex;
    }

    private function indexClientIdentity(
        int $id,
        int $countryId,
        bool $hasTaxId,
        ?string $legalName,
        ?string $commercialName
    ): void {
        foreach ([$legalName, $commercialName] as $candidateName) {
            $key = $this->normalizeClientIdentityName((string) $candidateName);

            if ($key !== '') {
                $this->clientIdentityIndex[$key][$id] = [
                    'country_id' => $countryId,
                    'has_tax_id' => $hasTaxId,
                ];
            }
        }
    }
}
