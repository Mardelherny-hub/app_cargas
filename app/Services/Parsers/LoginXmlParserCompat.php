<?php

namespace App\Services\Parsers;

use App\Models\BillOfLading;
use App\Models\Port;
use App\Models\Vessel;
use App\Models\Voyage;
use App\ValueObjects\ManifestParseResult;
use Exception;

/**
 * Compatibilidad del importador Login con los criterios operativos vigentes.
 */
class LoginXmlParserCompat extends LoginXmlParser
{
    protected array $importOptions = [];

    public function parse(
        string $filePath,
        array $options = []
    ): ManifestParseResult {
        $this->importOptions = $options;

        return parent::parse($filePath);
    }

    protected function createVoyage(
        array $data,
        array $context
    ): Voyage {
        $company = \App\Models\Company::findOrFail(
            $context['company_id']
        );

        $originPort = $this->findPortByName(
            $data['voyage']['origin_port']
        );
        $destinationPort = $this->findPortByName(
            $data['voyage']['destination_port']
        );

        if (!$originPort) {
            throw new Exception(
                "Puerto de origen '{$data['voyage']['origin_port']}' no encontrado en el sistema"
            );
        }

        if (!$destinationPort) {
            throw new Exception(
                "Puerto de destino '{$data['voyage']['destination_port']}' no encontrado en el sistema"
            );
        }

        $selectedVesselId = $this->importOptions['vessel_id'] ?? null;

        if ($selectedVesselId) {
            $leadVessel = Vessel::where('id', $selectedVesselId)
                ->where('company_id', $company->id)
                ->where('active', true)
                ->where('operational_status', 'active')
                ->first();

            if (!$leadVessel) {
                throw new Exception(
                    'La embarcación seleccionada no pertenece a la empresa o no está activa.'
                );
            }
        } else {
            $vesselName = trim(
                (string) ($data['voyage']['vessel_name'] ?? '')
            );

            if ($vesselName === '') {
                throw new Exception(
                    'Login no informa embarcación y no se seleccionó una embarcación.'
                );
            }

            $leadVessel = $this->findOrCreateVessel(
                $vesselName,
                $company->id
            );
        }

        $voyageNumber = trim(
            (string) ($this->importOptions['voyage_number'] ?? '')
        );

        if ($voyageNumber === '') {
            $voyageNumber = trim(
                (string) ($data['voyage']['voyage_number'] ?? '')
            );
        }

        if ($voyageNumber === '') {
            throw new Exception(
                'Login no informa número de viaje y no se ingresó uno en la importación.'
            );
        }

        $this->guardVoyageNumberIsFree($voyageNumber);

        return Voyage::create([
            'voyage_number' => $voyageNumber,
            'company_id' => $company->id,
            'lead_vessel_id' => $leadVessel->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destinationPort->country_id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destinationPort->id,
            'departure_date' => $data['voyage']['departure_date'],
            'estimated_arrival_date' => $data['voyage']['estimated_arrival_date'],
            'voyage_type' => $this->determineVoyageType($data),
            'cargo_type' => $this->determineCargoType($data),
            'status' => 'planning',
            'is_convoy' => false,
            'vessel_count' => 1,
            'total_cargo_capacity_tons' => 0,
            'total_container_capacity' => 0,
            'active' => true,
            'created_date' => now(),
            'created_by_user_id' => $context['user_id'],
        ]);
    }

    protected function createBillOfLadingFromData(
        array $blData,
        \App\Models\Shipment $shipment,
        array $context
    ): BillOfLading {
        $bill = parent::createBillOfLadingFromData(
            $blData,
            $shipment,
            $context
        );

        /*
         * La dirección del archivo se conserva como alternativa del BL, pero no
         * debe quedar seleccionada automáticamente sobre la ficha del cliente.
         */
        $bill->specificContacts()
            ->where('use_specific_data', true)
            ->update(['use_specific_data' => false]);

        $permiso = $this->extractPermisoEmbarqueFromDescription(
            $blData['cargo_description'] ?? null
        );

        if ($permiso !== null) {
            $bill->permiso_embarque = $permiso;
            $bill->saveQuietly();
        }

        return $bill;
    }

    /**
     * Ejemplo real Login: "P.E.: 26037EC01007976D" dentro de la descripción.
     */
    protected function extractPermisoEmbarqueFromDescription(
        ?string $description
    ): ?string {
        $description = trim((string) $description);

        if ($description === '') {
            return null;
        }

        if (!preg_match(
            '/\bP\.?\s*E\.?\s*:\s*([A-Z0-9]{8,30})\b/i',
            $description,
            $matches
        )) {
            return null;
        }

        return strtoupper(trim($matches[1]));
    }
}
