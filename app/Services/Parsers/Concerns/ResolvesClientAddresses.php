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
        $fileAddress = $this->cleanFileAddress($fileAddress);
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
        $fileAddress = $this->cleanFileAddress($fileAddress);
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

    /**
     * Limpia la dirección cruda del archivo: quita marcadores de identificación
     * fiscal con su número (RUC, R.U.C., CUIT, TAX ID, RUT, VAT), corta la cola
     * de contacto (TEL/PH/PHONE/FAX/CEL/ATTN/EMAIL/MAIL), quita emails sueltos
     * y etiquetas iniciales (ADD:/ADDRESS:), colapsa restos y recorta a 255.
     * Devuelve null si tras limpiar no queda dirección (ej. celda que solo
     * traía "CUIT: 30688415531"). Una dirección ya limpia pasa intacta.
     * Validado contra celdas reales de PARANA.xlsx y TFP (13/07/2026).
     */
    protected function cleanFileAddress(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        // Multilínea a una sola línea (TFP trae domicilios con saltos)
        $s = str_replace(["\r\n", "\r", "\n"], ' ', $raw);

        // Dos puntos fullwidth (U+FF1A) de archivos de origen chino -> ASCII
        $s = str_replace('：', ':', $s);

        // 0. Ruido temprano (*, #) ANTES de los patrones, para que "RUC#123" y "* ADDRESS:" matcheen
        $s = preg_replace('/[*#]+/', ' ', $s) ?? $s;

        // 1. Marcadores fiscales + su número: RUC, R.U.C., CUIT, TAX ID, TAXID, RUT, VAT NO,
        //    RUC / TAX ID, ID.FISCAL, RUC NUMBER/NRO/NO. Separadores: : espacio - + =
        $s = preg_replace('/\b(R\.?U\.?[CT]\.?\s*(\/\s*(TAX\s*ID|VAT))?|CUIT|NIT|C[NP]{2}J|TAX\s*ID|TAXID|VAT(\s*NO\.?)?|ID\.?\s*FISCAL)\s*(NUMBER|NBR\.?|NRO\.?|NO\.?)?\s*[:\s\-+=\.]\s*[0-9][0-9\-\.\s\/]*/i', ' ', $s) ?? $s;

        // 1b. TAX NUMBER / VAT con prefijo alfabetico de pais (ej. "TAX NUMBER:DE 120151991").
        //     Acotada a estos marcadores: un prefijo alfa generico se comeria calles ("RUTA 2").
        $s = preg_replace('/\b(TAX\s*NUMBER|VAT(\s*NO\.?)?)\s*[:\s\-+=\.]\s*[A-Z]{1,3}\s*[0-9][0-9\-\.\s\/]*/i', ' ', $s) ?? $s;

        // 2. Cortar la cola desde el primer marcador de contacto (incluye variantes ATN/ATT/CTC)
        $s = preg_replace('/\b(TEL|PH|PHONE|FAX|CEL|ATTN|ATN|ATTE|ATT|ATENCION|CTC|E-?MAIL|MAIL|CONTACTO?)\b\s*[:.]?.*$/i', '', $s) ?? $s;

        // 3. Bloques <email o nombre> completos, y emails sueltos remanentes
        $s = preg_replace('/<[^>]*>/', ' ', $s) ?? $s;
        $s = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', ' ', $s) ?? $s;

        // 4. Etiqueta inicial de dirección (con variantes cortas y typos vistos en archivos reales)
        $s = preg_replace('/^[\s,;.\-]*(ADDRESS|ADRRESS|ADRESS|ADD|AD|DIRECCI[OÓ]N|DIR|DOMICILIO|ENDERE[Çç]O)\s*:\s*/iu', '', $s) ?? $s;

        // Telefono sin marcador al final (queda tras quitar emails). Exige 8+ DIGITOS
        // para no comerse codigos postales tipo CEP "83.070-90" (7 digitos).
        $s = preg_replace('/[,;\s]*(?=(?:\D*\d){8,})\+?[\d(][\d\s\-()\/.]*\d\)?$/', '', $s) ?? $s;

        // 5. Espacios múltiples y separadores colgantes
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $s = trim($s, " ,;:-'");

        return $s === '' ? null : mb_substr($s, 0, 255);
    }
}