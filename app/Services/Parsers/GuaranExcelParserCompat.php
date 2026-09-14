<?php

namespace App\Services\Parsers;

use App\Models\BillOfLading;
use App\Models\ShipmentItem;
use App\Models\Vessel;
use App\Models\Voyage;
use Exception;

/**
 * Ajustes funcionales confirmados durante el smoke del 14/09/2026.
 */
class GuaranExcelParserCompat extends GuaranExcelParser
{
    protected function createVoyage(array $voyageData, array $options): Voyage
    {
        $user = auth()->user();
        $companyId = $user->userable_type === 'App\\Models\\Company'
            ? $user->userable_id
            : ($user->userable->company_id ?? null);

        if (!$companyId) {
            throw new Exception('Usuario sin empresa asignada');
        }

        $selectedVesselId = $options['vessel_id'] ?? null;

        if ($selectedVesselId) {
            $vessel = Vessel::where('id', $selectedVesselId)
                ->where('company_id', $companyId)
                ->where('active', true)
                ->where('operational_status', 'active')
                ->first();

            if (!$vessel) {
                throw new Exception(
                    'La embarcación seleccionada no pertenece a la empresa o no está activa.'
                );
            }
        } else {
            $sourceVesselName = trim(
                (string) ($voyageData['vessel_name'] ?? '')
            );

            if ($sourceVesselName === '') {
                throw new Exception(
                    'El archivo GUARAN no informa BARGE_NAME y no se seleccionó una embarcación.'
                );
            }

            $vessel = $this->findOrCreateVessel(
                $voyageData,
                (int) $companyId
            );
        }

        $sourceVoyageNumber = trim(
            (string) ($voyageData['voyage_number'] ?? '')
        );

        if ($sourceVoyageNumber === '') {
            $voyageData['voyage_number'] = trim(
                (string) ($options['voyage_number'] ?? '')
            );
        } else {
            $voyageData['voyage_number'] = $sourceVoyageNumber;
        }

        if (empty($voyageData['voyage_number'])) {
            throw new Exception('VOYAGE_NO es requerido en el archivo o en la importación');
        }

        $originPort = $this->resolvePortStrict($voyageData['pol']);
        $destPort = $this->resolvePortStrict($voyageData['pod']);

        $this->guardVoyageNumberIsFree($voyageData['voyage_number']);

        return Voyage::create(
            $this->buildVoyageCreationData(
                $voyageData,
                (int) $companyId,
                $vessel,
                $originPort,
                $destPort,
                $options
            )
        );
    }

    protected function createShipmentItem(
        BillOfLading $bill,
        array $row
    ): ShipmentItem {
        $lineNumber = $this->nextItemLineNumber($bill->id);
        $isContainerized = !empty($row['CONTAINER_NUMBER']);

        /*
         * Si la fila está vinculada a un contenedor, el ítem conserva la misma
         * clasificación canónica de contenedores que ya resolvió el BL.
         */
        $cargoTypeId = $isContainerized
            ? $bill->primary_cargo_type_id
            : $this->findCargoTypeByNCM($row['NCM']);

        $packagingTypeId = $isContainerized
            ? $bill->primary_packaging_type_id
            : $this->findPackagingTypeByName($row['PACK_TYPE']);

        $commodity = $this->buildCommodityClassification(
            $row['NCM'] ?? null
        );

        return ShipmentItem::create([
            'shipment_id' => $bill->shipment_id,
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,
            'item_description' => $this->buildCargoDescription($row),
            'cargo_type_id' => $cargoTypeId,
            'packaging_type_id' => $packagingTypeId,
            'package_quantity' => $this->parsePackageQuantity(
                $row['NUMBER_OF_PACKAGES'] ?? null
            ),
            'unit_of_measure' => 'KG',
            'cargo_marks' => $this->normalizeGuaranCargoMarks(
                $row['MARKS_DESCRIPTION'] ?? null
            ),
            'container_condition' => 'H',
            'operational_discharge_code' => '10073',
            'gross_weight_kg' => $this->parseWeight($row['GROSS_WEIGHT']),
            'net_weight_kg' => $this->parseWeight($row['NET_WEIGHT']),
            'volume_m3' => $this->parseVolume($row['VOLUME'] ?? null),
            'commodity_code' => $commodity['commodity_code'],
            'tariff_position' => $commodity['tariff_position'],
            'is_dangerous_goods' => !empty($row['UN_NUMBER']),
            'requires_refrigeration' => $this->requiresRefrigeration($row),
            'un_number' => $row['UN_NUMBER'] ?: null,
            'temperature_min' => $this->parseTemperature($row['TEMP_MIN']),
            'temperature_max' => $this->parseTemperature($row['TEMP_MAX']),
            'created_by_user_id' => auth()->id(),
        ]);
    }
}
