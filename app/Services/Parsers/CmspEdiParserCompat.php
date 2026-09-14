<?php

namespace App\Services\Parsers;

use App\Models\Client;
use App\Models\DocumentType;
use App\Models\Vessel;
use App\Models\Voyage;
use Exception;

/**
 * Compatibilidad para variantes CMSP/CUSCAR observadas durante el smoke del
 * 14/09/2026. Mantiene el parser base como fuente de lectura EDIFACT y sólo
 * ajusta reglas que deben resolverse antes de que el parseo pueda finalizar.
 */
class CmspEdiParserCompat extends CmspEdiParser
{
    /**
     * Los datos ingresados explícitamente por el operador tienen prioridad
     * sobre el TDT/DTM del archivo.
     */
    protected function createVoyage(array $data, array $options = []): Voyage
    {
        $user = auth()->user();
        $companyId = null;

        if ($user->company_id) {
            $companyId = $user->company_id;
        } elseif ($user->userable_type === 'App\\Models\\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } else {
            throw new Exception("Usuario no tiene empresa asignada. User ID: {$user->id}");
        }

        $originPort = $this->findOrCreatePort(
            $data['ports']['loading'] ?? 'ARBUE'
        );
        $destPort = $this->findOrCreatePort(
            $data['ports']['discharge'] ?? 'PYASU'
        );

        $vesselId = $options['vessel_id'] ?? null;

        if ($vesselId) {
            $vessel = Vessel::where('id', $vesselId)
                ->where('company_id', $companyId)
                ->first();

            if (!$vessel) {
                throw new Exception(
                    "Embarcación seleccionada con ID {$vesselId} no encontrada para la empresa."
                );
            }
        } else {
            $fileVesselName = trim(
                (string) ($data['vessel']['vessel_name'] ?? '')
            );

            if ($fileVesselName === '') {
                throw new Exception(
                    'El archivo CUSCAR no informa una embarcación y no se seleccionó una embarcación.'
                );
            }

            $vessel = $this->findOrCreateVessel(
                $fileVesselName,
                $companyId
            );
        }

        $voyageNumber = trim((string) ($options['voyage_number'] ?? ''));

        if ($voyageNumber === '') {
            $voyageNumber = trim(
                (string) ($data['vessel']['voyage_number'] ?? '')
            );
        }

        if ($voyageNumber === '') {
            throw new Exception('CMSP EDI: TDT sin número de viaje.');
        }

        $cargoType = $this->determineCargoTypeFromPorts(
            $originPort,
            $destPort
        );

        $this->guardVoyageNumberIsFree($voyageNumber);

        $departureDate = $options['departure_date']
            ?? ($data['dates']['departure'] ?? null);

        $estimatedArrivalDate = $options['discharge_date']
            ?? ($data['dates']['estimated_arrival'] ?? null);

        if ($estimatedArrivalDate === null) {
            throw new Exception(
                'El archivo CUSCAR no informa la fecha estimada de llegada DTM+132 y no se ingresó una fecha de descarga.'
            );
        }

        if ($departureDate !== null) {
            $departureTimestamp = strtotime((string) $departureDate);
            $estimatedArrivalTimestamp = strtotime(
                (string) $estimatedArrivalDate
            );

            if (
                $departureTimestamp === false
                || $estimatedArrivalTimestamp === false
            ) {
                throw new Exception(
                    'No se pudo validar la fecha de salida o la fecha estimada de llegada del viaje.'
                );
            }

            if (
                date('Y-m-d', $departureTimestamp)
                > date('Y-m-d', $estimatedArrivalTimestamp)
            ) {
                throw new Exception(
                    'La fecha de salida no puede ser posterior a la fecha estimada de llegada.'
                );
            }
        }

        return Voyage::create([
            'company_id' => $companyId,
            'voyage_number' => $voyageNumber,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destPort->country_id,
            'departure_date' => $departureDate,
            'estimated_arrival_date' => $estimatedArrivalDate,
            'status' => 'planning',
            'cargo_type' => $cargoType,
            'created_by_user_id' => auth()->id(),
            'manifest_format' => 'CMSP_EDI_CUSCAR',
            'import_source' => 'cmsp_edi_parser',
        ]);
    }

    /**
     * Un identificador explícitamente rotulado pero con longitud imposible se
     * ignora como número fiscal y se informa como advertencia. Nunca se recorta
     * ni se transforma en otro CUIT/RUC.
     */
    protected function extractExplicitTaxTypeFromText(
        ?string $text,
        ?string $expectedTaxId = null
    ): ?string {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $patterns = [
            'CUIT' => '/\bCUIT\b\s*(?:(?:NBR|NRO|Nº|N°)\.?\s*)?[:#?.-]*\s*([0-9][0-9.\/-]{5,20})/iu',
            'CNPJ' => '/\bCNPJ\b\s*[:#?.-]*\s*([0-9][0-9.\/-]{5,20})/iu',
            'RUC' => '/\bR\.?\s*U\.?\s*C\.?\b\s*[:#?.-]*\s*([0-9][0-9.\/-]{5,20})/iu',
            'NIT' => '/\bNIT\b\s*[:#?.-]*\s*([0-9][0-9.\/-]{5,20})/iu',
        ];

        foreach ($patterns as $taxType => $pattern) {
            if (!preg_match($pattern, $text, $matches)) {
                continue;
            }

            $taxId = preg_replace('/\D/', '', $matches[1]);

            if (
                $taxId === ''
                || preg_match('/^0+$/', $taxId)
                || !$this->isTaxIdCompatibleWithType($taxId, $taxType)
            ) {
                $rawTaxId = trim((string) ($matches[1] ?? ''));
                $warning =
                    "CMSP: se ignoró {$taxType} con formato incompatible informado en NAD: {$rawTaxId}.";

                if (!in_array($warning, $this->stats['warnings'], true)) {
                    $this->stats['warnings'][] = $warning;
                }

                return $taxType;
            }

            if ($expectedTaxId !== null) {
                $expected = preg_replace('/\D/', '', $expectedTaxId);

                if ($expected !== '' && $expected !== $taxId) {
                    throw new \DomainException(
                        "CMSP: el identificador {$taxType} escrito en NAD "
                        . 'no coincide con el identificador fiscal resuelto.'
                    );
                }
            }

            return $taxType;
        }

        return null;
    }

    protected function isTaxIdCompatibleWithType(
        string $taxId,
        string $taxType
    ): bool {
        $length = strlen($taxId);

        return match ($taxType) {
            'CUIT' => $length === 11,
            'CNPJ' => $length === 14,
            'RUC' => $length >= 7 && $length <= 10,
            'NIT' => $length >= 9 && $length <= 10,
            default => false,
        };
    }

    /**
     * Cuando el archivo declaró el tipo fiscal pero el número era inválido,
     * puede reutilizarse una única ficha exacta de mismo nombre y país. Si no
     * existe una coincidencia inequívoca, se mantiene el comportamiento base y
     * el dato fiscal queda vacío.
     */
    protected function findOrCreateClient(?array $partyData): ?Client
    {
        if (!$partyData || empty($partyData['name'])) {
            return null;
        }

        $user = auth()->user();
        $companyId = $user->company_id
            ?? (
                $user->userable_type === 'App\\Models\\Company'
                    ? $user->userable_id
                    : null
            );

        if (!$companyId) {
            throw new Exception(
                "Usuario no tiene empresa asignada. User ID: {$user->id}"
            );
        }

        $identity = $this->resolvePartyTaxIdentity($partyData);
        $taxId = $identity['tax_id'];
        $taxType = $identity['tax_type'];
        $countryId = $this->resolveClientCountryId(
            $partyData,
            $taxType
        );

        if ($taxId !== null) {
            $client = Client::query()
                ->where('tax_id', $taxId)
                ->where('country_id', $countryId)
                ->first();

            if ($client) {
                $this->persistClientAddress(
                    $client,
                    $partyData['address'] ?? null
                );

                return $client;
            }
        } else {
            $name = trim((string) $partyData['name']);
            $normalizedName = mb_strtoupper($name);

            if ($taxType !== null) {
                $matches = Client::query()
                    ->where('country_id', $countryId)
                    ->where(function ($query) use ($normalizedName) {
                        $query
                            ->whereRaw(
                                'UPPER(TRIM(legal_name)) = ?',
                                [$normalizedName]
                            )
                            ->orWhereRaw(
                                'UPPER(TRIM(commercial_name)) = ?',
                                [$normalizedName]
                            );
                    })
                    ->limit(2)
                    ->get();

                if ($matches->count() === 1) {
                    $client = $matches->first();
                    $this->persistClientAddress(
                        $client,
                        $partyData['address'] ?? null
                    );

                    return $client;
                }
            }

            $client = Client::query()
                ->whereNull('tax_id')
                ->where('country_id', $countryId)
                ->where(function ($query) use ($normalizedName) {
                    $query
                        ->whereRaw(
                            'UPPER(TRIM(legal_name)) = ?',
                            [$normalizedName]
                        )
                        ->orWhereRaw(
                            'UPPER(TRIM(commercial_name)) = ?',
                            [$normalizedName]
                        );
                })
                ->first();

            if ($client) {
                $this->persistClientAddress(
                    $client,
                    $partyData['address'] ?? null
                );

                return $client;
            }
        }

        $documentTypeId = null;

        if ($taxId !== null && $taxType !== null) {
            $documentTypeId = DocumentType::query()
                ->where('code', $taxType)
                ->where('country_id', $countryId)
                ->where('active', true)
                ->value('id');

            if (!$documentTypeId) {
                throw new \DomainException(
                    "CMSP: no existe un tipo documental {$taxType} "
                    . 'activo y compatible con el país resuelto.'
                );
            }
        }

        $client = Client::create([
            'created_by_company_id' => $companyId,
            'legal_name' => $partyData['name'],
            'commercial_name' => $partyData['name'],
            'tax_id' => $taxId,
            'country_id' => $countryId,
            'document_type_id' => $documentTypeId,
        ]);

        $this->persistClientAddress(
            $client,
            $partyData['address'] ?? null
        );

        return $client;
    }
}
