<?php
namespace App\Services\Parsers\Concerns;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
/**
 * Trait ResolvesClientAddresses
 *
 * ETAPA 1 - persistClientAddress():
 *   Si el cliente NO tiene dirección en su ficha (client_contact_data), la
 *   registra con la dirección del archivo:
 *     - Si ya hay contacto primario SIN dirección -> le agrega address_line_1.
 *     - Si NO hay contacto primario -> crea uno con is_primary = true.
 *
 * ETAPA 2 - resolveSpecificAddress():
 *   Si el cliente YA tiene dirección y la del archivo difiere (normalizada),
 *   devuelve el array para $bl->specificContacts()->create([...]) con la
 *   dirección específica de ese conocimiento, para ese rol. Si no, null.
 *
 * NOTA: el vínculo del contacto primario es client_contact_data.is_primary,
 * NO clients.primary_contact_data_id (esa columna no existe en la tabla).
 */
trait ResolvesClientAddresses
{
    /**
     * Normaliza: mayúsculas, trim y colapso de espacios. NO elimina acentos
     * (CORDOBA y CÓRDOBA se consideran distintas). '' si viene vacía/null.
     */
    protected function normalizeAddress(?string $address): string
    {
        if ($address === null) {
            return '';
        }
        $normalized = mb_strtoupper(trim($address));
        $normalized = preg_replace('/\s+/u', ' ', $normalized);
        return $normalized ?? '';
    }
    /**
     * ETAPA 1: registra la dirección del archivo en la ficha del cliente,
     * solo si el cliente todavía no tiene dirección registrada.
     */
    protected function persistClientAddress(?Client $client, ?string $fileAddress): bool
    {
        if (!$client) {
            return false;
        }
        if ($this->normalizeAddress($fileAddress) === '') {
            return false;
        }
        if ($this->clientStoredAddress($client) !== '') {
            return false;
        }
        $address = trim($fileAddress);
        $primary = $client->contactData()->where('is_primary', true)->first();
        if ($primary) {
            $primary->address_line_1 = $address;
            $primary->save();
            Log::info('Direccion agregada a contacto primario existente', [
                'client_id'  => $client->id,
                'contact_id' => $primary->id,
            ]);
            return true;
        }
        $contact = $client->contactData()->create([
            'address_line_1'     => $address,
            'is_primary'         => true,
            'active'             => true,
            'contact_type'       => 'general',
            'created_by_user_id' => auth()->id(),
        ]);
        Log::info('Direccion de cliente persistida en ficha nueva', [
            'client_id'  => $client->id,
            'contact_id' => $contact->id,
        ]);
        return true;
    }
    /**
     * ETAPA 2: si el cliente ya tiene dirección y la del archivo difiere,
     * devuelve el array para crear la dirección específica del BL en ese rol.
     */
    protected function resolveSpecificAddress(?Client $client, ?string $fileAddress, string $role): ?array
    {
        if (!$client) {
            return null;
        }
        $fileNorm = $this->normalizeAddress($fileAddress);
        if ($fileNorm === '') {
            return null;
        }

        // Contacto primario del cliente (la dirección "oficial" que estamos comparando).
        $primary = $client->contactData()->where('is_primary', true)->first()
            ?? $client->contactData()->first();

        // Sin contacto en la ficha no hay contra qué comparar ni a qué colgar la específica.
        if (!$primary) {
            return null;
        }

        $storedNorm = $this->normalizeAddress($primary->address_line_1);
        if ($storedNorm === '') {
            return null;
        }
        if ($storedNorm === $fileNorm) {
            return null;
        }

        // Difieren -> dirección específica del BL, colgada del contacto del cliente.
        return [
            'client_contact_data_id'  => $primary->id,
            'role'                    => $role,
            'use_specific_data'       => true,
            'specific_address_line_1' => trim($fileAddress),
            'created_by_user_id'      => auth()->id(),
        ];
    }
    /**
     * Dirección registrada del cliente (address_line_1 del contacto primario),
     * normalizada. '' si no tiene.
     */
    protected function clientStoredAddress(Client $client): string
    {
        $line1 = $client->contactData()->where('is_primary', true)->value('address_line_1');
        if ($line1 === null || trim($line1) === '') {
            $line1 = $client->contactData()->value('address_line_1');
        }
        return $this->normalizeAddress($line1);
    }
}