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

    /**
     * Recupera marcas sólo cuando el propio TFP las identifica explícitamente.
     * También conserva N/M cuando viene como línea propia ("no marks").
     */
    protected function extractTfpCargoMarks(?string $text): ?string
    {
        $text = (string) $text;

        if (preg_match(
            '/(?:Marks?\s*(?:and|&)\s*(?:Numbers?|Nos?)|Shipping\s+Marks?|Marks?)'
            . '[ \t]*:[ \t]*(?:\R[ \t]*)?([^\r\n]+)/i',
            $text,
            $matches
        )) {
            $marks = trim((string) $matches[1]);

            return $marks !== '' ? $marks : null;
        }

        if (preg_match('/^\s*(N\/M)\s*$/mi', $text, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * Recupera la posición arancelaria sólo de etiquetas explícitas del TFP.
     * V.470 trae CODARMONIZADO vacío, pero declara NCM y HS CODE en la
     * descripción de mercadería. No se interpreta ningún número sin etiqueta.
     */
    protected function extractTfpTariffPosition(?string $text): ?string
    {
        $text = (string) $text;

        /*
         * La posición AFIP conserva el código que trae la fuente. No se usa
         * normalizeNcm() aquí porque esa regla histórica reduce commodity_code
         * a 4+2 dígitos y perdería detalle válido (ej. 2930.90.39).
         *
         * El margen de texto entre etiqueta y código cubre variantes reales
         * como "HS CODE/NCM:151190" y "NCM\nDESCRIPTION\n3923" sin tomar
         * números que no estén precedidos por una etiqueta arancelaria.
         */
        if (!preg_match(
            '/(?:\bN\.?C\.?M\.?\b|\bHS\s*[- ]?CODES?\b)'
            . '[^0-9]{0,24}'
            . '([0-9]{4,15}(?:\.[0-9]{1,4})*)/i',
            $text,
            $matches
        )) {
            return null;
        }

        return $this->normalizeTfpTariffPosition($matches[1]);
    }

    protected function normalizeTfpTariffPosition(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if (!preg_match(
            '/([0-9]{4,15}(?:\.[0-9]{1,4})*)/',
            $raw,
            $matches
        )) {
            return null;
        }

        $value = trim($matches[1]);
        if (mb_strlen($value) > 16) {
            return null;
        }

        return $value;
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
            $packagesRaw === ''
            || !is_numeric($packagesRaw)
            || (float) $packagesRaw < 0
        ) {
            throw new Exception(
                'TFP: cantidad de bultos ausente o inválida.'
            );
        }

        if (
            !$hasContainers
            && !$isEmptyContainerItem
            && (float) $packagesRaw === 0.0
        ) {
            throw new Exception(
                'TFP: cantidad de bultos debe ser mayor a cero para carga suelta.'
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

        $cargoMarks = $this->extractTfpCargoMarks($description);

        $tariffPosition = !empty($data['cod_armonizado'])
            ? $this->normalizeTfpTariffPosition(
                $data['cod_armonizado']
            )
            : $this->extractTfpTariffPosition(
                $data['naturaleza_mercaderia'] ?? null
            );
        $commodityCode = $tariffPosition !== null
            ? $this->normalizeNcm($tariffPosition)
            : null;

        return ShipmentItem::create([
            'bill_of_lading_id' => $bill->id,
            'line_number' => $lineNumber,
            'item_description' => $description,
            'package_quantity' => $packageQuantity,
            'gross_weight_kg' => $grossWeight,
            'cargo_marks' => $cargoMarks,
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
            // commodity_code mantiene la regla histórica TFP
            // acordada (4+2). tariff_position conserva el código fuente con
            // su detalle porque alimenta el campo AFIP y admite puntos.
            'commodity_code' => $commodityCode,
            'tariff_position' => $tariffPosition,
            'imdg_class' => ($data['imdg'] ?? null) !== null
                && trim((string) $data['imdg']) !== ''
                    ? trim((string) $data['imdg'])
                    : null,
            'webservice_data' => [
                'tfp' => [
                    // null = campo ausente; '' = campo presente pero vacío.
                    'es_combustible' => $data['es_combustible'] ?? null,
                    'contenedor' => $data['contenedor'] ?? null,
                ],
            ],
            'created_by_user_id' => auth()->id(),
        ]);
    }

    protected function findOrCreateContainerType(string $code): ContainerType
    {
        $code = strtoupper(trim($code));

        /*
         * Si el catálogo real ya conoce el código informado por TFP, se conserva
         * exactamente. Esto cubre códigos operativos existentes como 20TN,
         * 40RH y 40OT sin mantener aliases duplicados en el parser.
         */
        $exact = ContainerType::where('code', $code)
            ->where('active', true)
            ->first();

        if ($exact) {
            return $exact;
        }

        /*
         * Alias confirmados del formato TFP:
         * - DV representa el contenedor general del mismo tamaño.
         * - 40RF aparece en BM ROSA V.470-N y la propia descripción de ambos
         *   casos declara "40 REEF 9'6". En el catálogo de la aplicación esa
         *   unidad corresponde a 40RH (reefer High Cube, ISO 45R1).
         * - 40OT sólo cae a 40GP en entornos históricos donde el catálogo todavía
         *   no incorporó el Open Top específico.
         */
        $mapping = [
            '20DV' => '20GP',
            '40DV' => '40GP',
            '40RF' => '40RH',
            '40OT' => '40GP',
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
