<?php

namespace App\Services\Parsers;

use App\Models\BillOfLading;
use App\Models\ContainerType;
use App\Models\ShipmentItem;
use App\Models\Vessel;
use App\Models\Voyage;
use Exception;

/**
 * Compatibilidad TFP para los casos confirmados durante el smoke:
 * embarcación seleccionada, viaje operativo, vacíos y tipos 40RH/40OT.
 */
class TfpTextParserCompat extends TfpTextParser
{
    protected function findOrCreateVoyage(
        array $data,
        array $options = []
    ): Voyage {
        $user = auth()->user();

        if (!$user) {
            throw new Exception('TFP requiere usuario autenticado.');
        }

        $companyId = null;

        if ($user->userable_type === 'App\\Models\\Company' && $user->userable_id) {
            $companyId = (int) $user->userable_id;
        } elseif ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
            $companyId = (int) $user->userable->company_id;
        }

        if (!$companyId) {
            throw new Exception('Usuario no tiene empresa asignada.');
        }

        $vessel = Vessel::find($options['vessel_id'] ?? null);

        if (!$vessel) {
            throw new Exception('TFP: vessel_id es obligatorio.');
        }

        if ((int) $vessel->company_id !== $companyId) {
            throw new Exception(
                'El vessel seleccionado no pertenece a la empresa importadora.'
            );
        }

        $originPort = $this->findOrCreatePort($data['pol']);
        $destPort = $this->findOrCreatePort($data['pod']);

        /*
         * TFP no informa un número de viaje en la fuente. El parser base genera
         * una clave técnica TFP-<hash> para mantener determinismo. Si el operador
         * ingresó el número real, ese dato reemplaza únicamente esa clave técnica.
         */
        $voyageNumber = trim(
            (string) ($options['voyage_number'] ?? '')
        );

        if ($voyageNumber === '') {
            $voyageNumber = trim(
                (string) ($data['voyage_number'] ?? '')
            );
        }

        if ($voyageNumber === '') {
            throw new Exception(
                'TFP no informa número de viaje y no se pudo generar una clave técnica.'
            );
        }

        $this->guardVoyageNumberIsFree($voyageNumber, (int) $vessel->id);

        return Voyage::create([
            'voyage_number' => $voyageNumber,
            'company_id' => $companyId,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $originPort->id,
            'destination_port_id' => $destPort->id,
            'origin_country_id' => $originPort->country_id,
            'destination_country_id' => $destPort->country_id,
            'status' => 'planning',
            'voyage_type' => 'single_vessel',
            'cargo_type' => $this->resolveTfpVoyageCargoType(
                $companyId,
                $originPort,
                $destPort
            ),
            'departure_date' => null,
            'estimated_arrival_date' => null,
            'total_cargo_capacity_tons' => $vessel->cargo_capacity_tons,
            'total_container_capacity' => $vessel->container_capacity ?? 0,
            'total_cargo_weight_loaded' => 0,
            'total_containers_loaded' => 0,
            'capacity_utilization_percentage' => 0,
        ]);
    }

    /**
     * TFP usa tanto VACIO como VACIOS para representar mercadería vacía.
     *
     * Caso real confirmado:
     * DTY260626N.txt / BL DT0626BUESEG77
     * CANTTOTALBULTOS=0 y PESOTOTALBULTOS=.000.
     */
    protected function isEmptyCargoDescription(?string $description): bool
    {
        $normalized = mb_strtoupper(
            trim((string) $description)
        );

        return in_array(
            $normalized,
            ['VACIO', 'VACIOS'],
            true
        );
    }

    protected function createShipmentItem(
        BillOfLading $bill,
        array $data,
        bool $hasContainers = false
    ): ?ShipmentItem {
        $lineNumber = ShipmentItem::where(
            'bill_of_lading_id',
            $bill->id
        )->max('line_number') ?? 0;
        $lineNumber++;

        $description = trim(
            (string) ($data['naturaleza_mercaderia'] ?? '')
        );
        $packagesRaw = trim(
            (string) ($data['cant_total_bultos'] ?? '')
        );
        $grossRaw = trim(
            (string) ($data['peso_total_bultos'] ?? '')
        );

        if ($description === '') {
            throw new Exception('TFP: mercadería sin descripción.');
        }

        $isEmptyContainerItem = $this->isEmptyCargoDescription(
            $description
        );

        if (
            !$isEmptyContainerItem
            && (
                $packagesRaw === ''
                || !is_numeric($packagesRaw)
                || (float) $packagesRaw <= 0
            )
        ) {
            throw new Exception(
                'TFP: cantidad de bultos ausente o inválida.'
            );
        }

        if (
            !$isEmptyContainerItem
            && (
                $grossRaw === ''
                || !is_numeric($grossRaw)
                || (float) $grossRaw <= 0
            )
        ) {
            throw new Exception(
                'TFP: peso bruto ausente o inválido.'
            );
        }

        $packageQuantity = $isEmptyContainerItem
            ? 0
            : (int) floatval($packagesRaw);
        $grossWeight = $isEmptyContainerItem
            ? 0.0
            : floatval($grossRaw);

        return ShipmentItem::create([
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,
            'item_description' => $description,
            'package_quantity' => $packageQuantity,
            'gross_weight_kg' => $grossWeight,
            'net_weight_kg' => null,
            'volume_m3' => isset($data['volumen_total'])
                && trim((string) $data['volumen_total']) !== ''
                    ? floatval($data['volumen_total'])
                    : null,
            'cargo_type_id' => $hasContainers
                ? \App\Models\CargoType::where('code', 'CON001')
                    ->where('active', true)
                    ->firstOrFail()
                    ->id
                : null,
            /*
             * Regresión confirmada contra el criterio ya aplicado en julio:
             * si el ítem pertenece a un BL con contenedores, el tipo general de
             * embalaje de la aplicación es CONTENEDOR. El detalle específico
             * declarado por TFP (CARTONS, BAGS, etc.) se conserva aparte y no
             * se fuerza a un catálogo que no lo representa fielmente.
             */
            'packaging_type_id' => $hasContainers
                ? \App\Models\PackagingType::where('code', 'T')
                    ->where('active', true)
                    ->firstOrFail()
                    ->id
                : null,
            'package_type_description' => trim(
                (string) ($data['tipo_embalaje'] ?? '')
            ) ?: null,
            'commodity_code' => !empty($data['cod_armonizado'])
                ? $this->normalizeNcm($data['cod_armonizado'])
                : $this->extractNcmFromText(
                    $data['naturaleza_mercaderia'] ?? null
                ),
            'tariff_position' => null,
            'created_by_user_id' => auth()->id(),
        ]);
    }

    protected function findOrCreateContainerType(string $code): ContainerType
    {
        $code = strtoupper(trim($code));

        /*
         * BM ROSA V.468 trae 40OT real. El catálogo base de la aplicación no
         * define 40OT, pero el criterio histórico ya usado por Guaran para un
         * tipo comercial sin catálogo específico es conservar el tamaño y usar
         * el tipo general de ese tamaño. Por eso 40OT cae a 40GP, nunca a 20GP.
         *
         * Si el catálogo del entorno sí incorporó 40OT, se conserva exacto.
         */
        if ($code === '40OT') {
            $exactOpenTop = ContainerType::where('code', '40OT')
                ->where('active', true)
                ->first();

            if ($exactOpenTop) {
                return $exactOpenTop;
            }

            $this->stats['warnings'][] =
                "Tipo contenedor '40OT' mapeado a '40GP' por catálogo sin 40OT.";

            $code = '40GP';
        }

        $mapping = [
            '20DV' => '20GP',
            '40DV' => '40GP',
            '20GP' => '20GP',
            '40GP' => '40GP',
            '40HC' => '40HC',
            '40RH' => '40RH',
        ];

        if (!isset($mapping[$code])) {
            throw new Exception(
                "TFP: tipo de contenedor '{$code}' no soportado."
            );
        }

        $mappedCode = $mapping[$code];

        $type = ContainerType::where('code', $mappedCode)
            ->where('active', true)
            ->first();

        if (!$type) {
            throw new Exception(
                "TFP: tipo '{$mappedCode}' no existe activo en el catálogo."
            );
        }

        return $type;
    }
}
