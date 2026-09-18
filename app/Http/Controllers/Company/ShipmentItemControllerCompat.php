<?php

namespace App\Http\Controllers\Company;

use App\Models\CargoType;
use App\Models\Client;
use App\Models\ContainerType;
use App\Models\PackagingType;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ajustes de edición para los formatos importados que conservan información
 * de contenedor que no puede validarse como si fuera una carga manual.
 */
class ShipmentItemControllerCompat extends ShipmentItemController
{
    public function edit(ShipmentItem $shipmentItem)
    {
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para editar items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        if (!$this->canAccessCompany($shipmentItem->shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para editar este item.');
        }

        if ($this->isUser() && $this->isOperator()) {
            if ($shipmentItem->shipment->created_by_user_id !== Auth::id()) {
                abort(403, 'No tiene permisos para editar este item.');
            }
        }

        $cargoTypes = CargoType::where('active', true)
            ->orderBy('name')
            ->get();
        $packagingTypes = PackagingType::where('active', true)
            ->orderBy('name')
            ->get();
        $clients = Client::where('status', 'active')
            ->orderBy('legal_name')
            ->get();
        $containerTypes = ContainerType::where('active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        /*
         * La distribución física por contenedor puede ser desconocida.
         * El pivot container_shipment_item admite package_quantity y
         * gross_weight_kg nulos; no depende del formato que originó el dato.
         */
        $allowsUnknownContainerDistribution = true;

        $containerData = [];

        foreach ($shipmentItem->containers()->with('containerType')->get() as $container) {
            $pivot = $container->pivot;
            $sourceSeals = $this->decodeSourceSeals(
                $pivot->source_seals ?? null
            );
            $sourceSealNumber = $this->firstSourceSealNumber($sourceSeals);

            if ($container->carrier_seal) {
                $sealNumber = $container->carrier_seal;
                $sealSource = 'carrier';
            } elseif ($container->shipper_seal) {
                $sealNumber = $container->shipper_seal;
                $sealSource = 'shipper';
            } elseif ($sourceSealNumber !== null) {
                $sealNumber = $sourceSealNumber;
                $sealSource = 'source';
            } else {
                $sealNumber = null;
                $sealSource = 'shipper';
            }

            $containerData[] = [
                'id' => $container->id,
                'container_number' => $container->container_number,
                'container_type_id' => $container->container_type_id,
                'seal_number' => $sealNumber,
                'seal_source' => $sealSource,
                'tare_weight' => $container->tare_weight_kg,
                'condition' => $container->condition ?? 'L',
                'package_quantity' => $pivot->package_quantity,
                'gross_weight_kg' => $pivot->gross_weight_kg,
                'verified_gross_mass_kg' => $pivot->verified_gross_mass_kg,
                'net_weight_kg' => $pivot->net_weight_kg,
                'volume_m3' => $pivot->volume_m3,
                'loading_sequence' => $pivot->loading_sequence,
            ];
        }

        return view('company.shipment-items.edit', compact(
            'shipmentItem',
            'cargoTypes',
            'packagingTypes',
            'clients',
            'containerTypes',
            'containerData',
            'allowsUnknownContainerDistribution'
        ));
    }

    public function update(Request $request, ShipmentItem $shipmentItem)
    {
        if (!$this->canPerform('view_cargas')) {
            abort(403, 'No tiene permisos para editar items de shipments.');
        }

        if (!$this->hasCompanyRole('Cargas')) {
            abort(403, 'Su empresa no tiene el rol de Cargas.');
        }

        if (!$this->canAccessCompany($shipmentItem->shipment->voyage->company_id)) {
            abort(403, 'No tiene permisos para editar este item.');
        }

        if (!$this->canManageShipmentItemsCompat($shipmentItem->shipment)) {
            return redirect()->route('company.shipment-items.show', $shipmentItem)
                ->with('error', 'No puede editar items de este shipment en su estado actual.');
        }

        if ($this->isUser() && $this->isOperator()) {
            if ($shipmentItem->shipment->created_by_user_id !== Auth::id()) {
                abort(403, 'No tiene permisos para editar este item.');
            }
        }

        $sourceFormat = strtoupper(trim((string) (
            optional($shipmentItem->billOfLading)->source_format
            ?: optional($shipmentItem->shipment->voyage)->manifest_format
        )));

        $isLoginItem = $sourceFormat === 'LOGIN_XML';
        $isCmspItem = $sourceFormat === 'CMSP_EDI_CUSCAR';

        /*
         * La distribución por contenedor es opcional en el modelo canónico.
         * NULL o 0 no implica que el contenedor esté vacío: puede significar
         * simplemente que no se conoce la distribución física de los bultos.
         */
        $allowsUnknownContainerDistribution = true;
        $allowsUnknownContainerPackages = true;

        /*
         * La excepción de tipo de embalaje conserva por ahora su comportamiento
         * existente; es una regla distinta a la distribución por contenedor.
         */
        $allowsUnknownPackaging =
            $isLoginItem || $isCmspItem;

        $containersInput = $request->input('containers', []);
        $isContainerCargoInput = $this->isContainerizedCargoCompat(
            (int) $request->input('cargo_type_id')
        );

        $allContainersEmpty =
            $isContainerCargoInput
            && is_array($containersInput)
            && $containersInput !== []
            && collect($containersInput)->every(
                fn ($container) => ($container['condition'] ?? 'L') === 'V'
            );

        /*
         * Un ítem cuyos contenedores están todos vacíos se valida como vacío
         * por su estado real, no por el formato que originó la importación.
         * Esto evita depender de source_format/manifest_format para permitir
         * los campos generales que legítimamente pueden quedar sin dato.
         */
        $isImportedEmptyItem = $allContainersEmpty;

        if ($isImportedEmptyItem) {
            $request->merge([
                'item_description' => $request->input('item_description')
                    ?: $shipmentItem->item_description,
                'package_quantity' => $request->input('package_quantity') === null
                    || $request->input('package_quantity') === ''
                        ? (int) ($shipmentItem->package_quantity ?? 0)
                        : $request->input('package_quantity'),
                'gross_weight_kg' => $request->input('gross_weight_kg') === null
                    || $request->input('gross_weight_kg') === ''
                        ? (float) ($shipmentItem->gross_weight_kg ?? 0)
                        : $request->input('gross_weight_kg'),
            ]);
        }

        $minPackageQuantity =
            ($allowsUnknownContainerPackages || $allContainersEmpty) ? 0 : 1;

        $rules = [
            'line_number' => 'required|integer|min:0',
            'item_reference' => 'nullable|string|max:100',
            'item_description' => $isImportedEmptyItem
                ? 'nullable|string|max:5000'
                : 'required|string|max:5000',
            'cargo_type_id' => 'required|exists:cargo_types,id,active,1',
            'packaging_type_id' => $allowsUnknownPackaging
                ? 'nullable|exists:packaging_types,id,active,1'
                : 'required|exists:packaging_types,id,active,1',
            'package_quantity' => 'required|integer|min:' . $minPackageQuantity,
            'gross_weight_kg' => 'required|numeric|min:0',
            'net_weight_kg' => 'nullable|numeric|min:0',
            'volume_m3' => 'nullable|numeric|min:0',
            'declared_value' => 'nullable|numeric|min:0',
            'currency_code' => $isImportedEmptyItem
                ? 'nullable|in:USD,ARS,PYG,EUR'
                : 'required|in:USD,ARS,PYG,EUR',
            'unit_of_measure' => $isImportedEmptyItem
                ? 'nullable|in:PCS,KG,LT,M3,BOX'
                : 'required|in:PCS,KG,LT,M3,BOX',
            'country_of_origin' => 'nullable|string|size:2',
            'cargo_marks' => 'nullable|string|max:500',
            'commodity_code' => 'nullable|string|max:20',
            'commodity_description' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:200',
            'lot_number' => 'nullable|string|max:50',
            'serial_number' => 'nullable|string|max:100',
            'is_dangerous_goods' => 'boolean',
            'is_perishable' => 'boolean',
            'is_fragile' => 'boolean',
            'requires_refrigeration' => 'boolean',
            'requires_permit' => 'boolean',
            'requires_inspection' => 'boolean',
            'un_number' => 'nullable|string|max:10',
            'imdg_class' => 'nullable|string|max:10',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric',
            'permit_number' => 'nullable|string|max:50',
            'inspection_type' => 'nullable|in:customs,quality,sanitary,security,environmental',
            'tariff_position' => 'nullable|string|max:16',
            'is_secure_logistics_operator' => 'nullable|in:S,N',
            'is_monitored_transit' => 'nullable|in:S,N',
            'is_renar' => 'nullable|in:S,N',
            'foreign_forwarder_name' => 'nullable|string|max:70',
            'foreign_forwarder_tax_id' => 'nullable|string|max:35',
            'foreign_forwarder_country' => 'nullable|string|max:3',
            'container_condition' => 'nullable|in:H,P',
            'package_numbers' => 'nullable|string|max:100',
            'packaging_type_code' => 'nullable|string|max:1',
            'discharge_customs_code' => 'nullable|string|max:3',
            'operational_discharge_code' => 'nullable|string|max:5',
            'comments' => 'nullable|string|max:60',
            'consignee_document_type' => 'nullable|string|max:4',
            'consignee_tax_id' => 'nullable|string|max:11',
            'containers' => 'sometimes|array',
            'containers.*.id' => 'nullable|integer',
            'containers.*.container_number' => 'required_with:containers|string|max:20',
            'containers.*.container_type_id' => 'required_with:containers|exists:container_types,id',
            'containers.*.seal_number' => 'nullable|string|max:50',
            'containers.*.seal_source' => 'nullable|in:carrier,shipper,source',
            'containers.*.tare_weight' => 'nullable|numeric|min:0',
            'containers.*.condition' => 'nullable|in:L,V',
            'containers.*.package_quantity' =>
                $allowsUnknownContainerDistribution
                    ? 'nullable|integer|min:0'
                    : 'required_with:containers|integer|min:0',
            'containers.*.gross_weight_kg' =>
                $allowsUnknownContainerDistribution
                    ? 'nullable|numeric|min:0'
                    : 'required_with:containers|numeric|min:0',
            'containers.*.net_weight_kg' => 'nullable|numeric|min:0',
            'containers.*.volume_m3' => 'nullable|numeric|min:0',
            'containers.*.loading_sequence' => 'nullable|string|max:10',
            'containers.*.notes' => 'nullable|string|max:500',
        ];

        $messages = [
            'required' =>
                'El campo :attribute es obligatorio.',
            'required_with' =>
                'El campo :attribute es obligatorio.',
            'exists' =>
                'El valor seleccionado para :attribute no es valido.',
            'integer' =>
                'El campo :attribute debe ser un numero entero.',
            'numeric' =>
                'El campo :attribute debe ser numerico.',
            'min' =>
                'El campo :attribute debe ser al menos :min.',
            'in' =>
                'El valor seleccionado para :attribute no es valido.',
        ];

        $attributes = [
            'line_number' => 'numero de linea',
            'item_description' => 'descripcion del item',
            'cargo_type_id' => 'tipo de carga',
            'packaging_type_id' => 'tipo de embalaje',
            'package_quantity' => 'cantidad de bultos',
            'gross_weight_kg' => 'peso bruto',
            'currency_code' => 'moneda',
            'unit_of_measure' => 'unidad de medida',
            'containers.*.container_number' => 'numero de contenedor',
            'containers.*.container_type_id' => 'tipo de contenedor',
            'containers.*.package_quantity' =>
                'cantidad de bultos del contenedor',
            'containers.*.gross_weight_kg' =>
                'peso de carga del contenedor',
        ];

        $validated = $request->validate(
            $rules,
            $messages,
            $attributes
        );

        if (!empty($validated['containers'])) {
            foreach ($validated['containers'] as $index => $containerData) {
                $condition = $containerData['condition'] ?? 'L';

                $packagesRaw =
                    $containerData['package_quantity'] ?? null;
                $grossWeightRaw =
                    $containerData['gross_weight_kg'] ?? null;

                $packages =
                    $packagesRaw === null || $packagesRaw === ''
                        ? null
                        : (int) $packagesRaw;

                $grossWeight =
                    $grossWeightRaw === null || $grossWeightRaw === ''
                        ? null
                        : (float) $grossWeightRaw;

                if ($condition === 'V') {
                    $tareWeight =
                        array_key_exists('tare_weight', $containerData)
                        && $containerData['tare_weight'] !== null
                        && $containerData['tare_weight'] !== ''
                            ? (float) $containerData['tare_weight']
                            : null;

                    $errors = [];

                    if (
                        $packages !== null
                        && !in_array($packages, [0, 1], true)
                    ) {
                        $errors["containers.{$index}.package_quantity"] =
                            'Un contenedor vacío debe tener 0 o 1 bulto.';
                    }

                    if (
                        $grossWeight !== null
                        && abs($grossWeight) > 0.00001
                        && (
                            $tareWeight === null
                            || abs($grossWeight - $tareWeight) > 0.01
                        )
                    ) {
                        $errors["containers.{$index}.gross_weight_kg"] =
                            'En un contenedor vacío, el peso bruto debe ser 0 o coincidir con la tara.';
                    }

                    if ($errors !== []) {
                        return redirect()->back()
                            ->withInput()
                            ->withErrors($errors);
                    }

                    continue;
                }

                if (
                    !$allowsUnknownContainerPackages
                    && ($packages === null || $packages < 1)
                ) {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors([
                            "containers.{$index}.package_quantity" =>
                                'Un contenedor con carga debe tener al menos 1 bulto.',
                        ]);
                }
            }
        }

        $isContainerCargo = $this->isContainerizedCargoCompat(
            (int) $validated['cargo_type_id']
        );

        if (!empty($validated['containers']) && !$isContainerCargo) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'containers' =>
                        'Solo se pueden asignar contenedores a carga contenedorizada.',
                ]);
        }

        if ($isContainerCargo && !empty($validated['containers'])) {
            $containers = collect($validated['containers']);
            $totalPackageQuantity = $containers->sum('package_quantity');
            $totalGrossWeight = $containers->sum('gross_weight_kg');

            $hasUnknownContainerPackages =
                $allowsUnknownContainerDistribution
                && $containers->contains(
                    fn ($container) =>
                        ($container['condition'] ?? 'L') !== 'V'
                        && (
                            !array_key_exists(
                                'package_quantity',
                                $container
                            )
                            || $container['package_quantity'] === null
                            || $container['package_quantity'] === ''
                            || (int) $container['package_quantity'] === 0
                        )
                );

            $hasUnknownContainerGrossWeight =
                $allowsUnknownContainerDistribution
                && $containers->contains(
                    fn ($container) =>
                        ($container['condition'] ?? 'L') !== 'V'
                        && (
                            !array_key_exists(
                                'gross_weight_kg',
                                $container
                            )
                            || $container['gross_weight_kg'] === null
                            || $container['gross_weight_kg'] === ''
                        )
                );

            if (
                !$hasUnknownContainerPackages
                && $totalPackageQuantity != $validated['package_quantity']
            ) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'containers' =>
                            "La suma de bultos en contenedores ({$totalPackageQuantity}) "
                            . "debe coincidir con el total del ítem ({$validated['package_quantity']}).",
                    ]);
            }

            if (
                !$allContainersEmpty
                && !$hasUnknownContainerGrossWeight
                && abs($totalGrossWeight - $validated['gross_weight_kg']) > 0.01
            ) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'containers' =>
                            "La suma de peso bruto en contenedores ({$totalGrossWeight}) "
                            . "debe coincidir con el total del ítem ({$validated['gross_weight_kg']}).",
                    ]);
            }
        }

        try {
            DB::beginTransaction();

            $itemData = [
                'line_number' => $validated['line_number'],
                'item_reference' => $validated['item_reference'] ?? null,
                'item_description' => $validated['item_description']
                    ?? $shipmentItem->item_description,
                'cargo_type_id' => $validated['cargo_type_id'],
                'packaging_type_id' =>
                    array_key_exists('packaging_type_id', $validated)
                        ? $validated['packaging_type_id']
                        : $shipmentItem->packaging_type_id,
                'package_quantity' => $validated['package_quantity'],
                'gross_weight_kg' => $validated['gross_weight_kg'],
                'net_weight_kg' => $validated['net_weight_kg'] ?? null,
                'volume_m3' => $validated['volume_m3'] ?? null,
                'declared_value' => $validated['declared_value'] ?? $shipmentItem->declared_value,
                'currency_code' => $validated['currency_code'] ?? $shipmentItem->currency_code,
                'unit_of_measure' => $validated['unit_of_measure'] ?? $shipmentItem->unit_of_measure,
                'country_of_origin' => $validated['country_of_origin'] ?? null,
                'cargo_marks' => $validated['cargo_marks'] ?? null,
                'commodity_code' => $validated['commodity_code'] ?? null,
                'commodity_description' => $validated['commodity_description'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'model' => $validated['model'] ?? null,
                'manufacturer' => $validated['manufacturer'] ?? null,
                'lot_number' => $validated['lot_number'] ?? null,
                'serial_number' => $validated['serial_number'] ?? null,
                'is_dangerous_goods' => $validated['is_dangerous_goods'] ?? false,
                'is_perishable' => $validated['is_perishable'] ?? false,
                'is_fragile' => $validated['is_fragile'] ?? false,
                'requires_refrigeration' => $validated['requires_refrigeration'] ?? false,
                'requires_permit' => $validated['requires_permit'] ?? false,
                'requires_inspection' => $validated['requires_inspection'] ?? false,
                'un_number' => $validated['un_number'] ?? null,
                'imdg_class' => $validated['imdg_class'] ?? null,
                'temperature_min' => $validated['temperature_min'] ?? null,
                'temperature_max' => $validated['temperature_max'] ?? null,
                'permit_number' => $validated['permit_number'] ?? null,
                'inspection_type' => $validated['inspection_type'] ?? null,
                'tariff_position' => $validated['tariff_position'] ?? null,
                'is_secure_logistics_operator' => $validated['is_secure_logistics_operator'] ?? 'N',
                'is_monitored_transit' => $validated['is_monitored_transit'] ?? 'N',
                'is_renar' => $validated['is_renar'] ?? 'N',
                'foreign_forwarder_name' => $validated['foreign_forwarder_name'] ?? null,
                'foreign_forwarder_tax_id' => $validated['foreign_forwarder_tax_id'] ?? null,
                'foreign_forwarder_country' => $validated['foreign_forwarder_country'] ?? null,
                'container_condition' => $validated['container_condition'] ?? null,
                'package_numbers' => $validated['package_numbers'] ?? null,
                'packaging_type_code' => $validated['packaging_type_code'] ?? null,
                'discharge_customs_code' => $validated['discharge_customs_code'] ?? null,
                'operational_discharge_code' => $validated['operational_discharge_code'] ?? null,
                'comments' => $validated['comments'] ?? null,
                'consignee_document_type' => $validated['consignee_document_type'] ?? null,
                'consignee_tax_id' => $validated['consignee_tax_id'] ?? null,
                'last_updated_date' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ];

            $shipmentItem->update($itemData);

            if ($isContainerCargo) {
                $this->updateItemContainersCompat(
                    $shipmentItem,
                    $validated['containers'] ?? []
                );
            } else {
                $shipmentItem->containers()->detach();
            }

            DB::commit();

            return redirect()->route(
                'company.bills-of-lading.show',
                $shipmentItem->billOfLading
            )->with('success', 'Item actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error actualizando ShipmentItem: ' . $e->getMessage(), [
                'item_id' => $shipmentItem->id,
                'user_id' => Auth::id(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al actualizar el item: ' . $e->getMessage());
        }
    }

    private function isContainerizedCargoCompat(int $cargoTypeId): bool
    {
        $cargoType = CargoType::find($cargoTypeId);
        if (!$cargoType) {
            return false;
        }

        $name = mb_strtolower((string) $cargoType->name);

        return str_contains($name, 'container')
            || str_contains($name, 'contenedor');
    }

    private function canManageShipmentItemsCompat(Shipment $shipment): bool
    {
        if (!in_array($shipment->status, ['planning', 'loading'], true)) {
            return false;
        }

        if ($this->isCompanyAdmin()) {
            return true;
        }

        if ($this->isUser() && $this->isOperator()) {
            return $shipment->created_by_user_id === Auth::id();
        }

        return false;
    }

    private function decodeSourceSeals(mixed $sourceSeals): array
    {
        if (is_array($sourceSeals)) {
            return $sourceSeals;
        }

        if (!is_string($sourceSeals) || trim($sourceSeals) === '') {
            return [];
        }

        $decoded = json_decode($sourceSeals, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function firstSourceSealNumber(array $sourceSeals): ?string
    {
        $first = $sourceSeals[0] ?? null;

        if (is_array($first)) {
            $value = trim((string) ($first['seal_number'] ?? ''));
            return $value !== '' ? $value : null;
        }

        if (is_string($first)) {
            $value = trim($first);
            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function updateItemContainersCompat(
        ShipmentItem $shipmentItem,
        array $containersData
    ): void {
        $existingPivot = [];

        foreach ($shipmentItem->containers()->get() as $existing) {
            $existingPivot[$existing->container_number] = [
                'source_seals' => $existing->pivot->source_seals,
                'source_line_numbers' => $existing->pivot->source_line_numbers,
                'verified_gross_mass_kg' => $existing->pivot->verified_gross_mass_kg,
                'container_condition' => $existing->pivot->container_condition,
            ];
        }

        $shipmentItem->containers()->detach();

        foreach ($containersData as $containerData) {
            $condition = $containerData['condition'] ?? 'L';
            $container = \App\Models\Container::where(
                'container_number',
                $containerData['container_number']
            )->first();

            $source = $containerData['seal_source'] ?? null;
            $sealValue = array_key_exists('seal_number', $containerData)
                ? ($containerData['seal_number'] !== ''
                    ? $containerData['seal_number']
                    : null)
                : null;

            if ($container) {
                $updates = [
                    'container_type_id' => $containerData['container_type_id'],
                    'tare_weight_kg' => array_key_exists('tare_weight', $containerData)
                        ? $containerData['tare_weight']
                        : $container->tare_weight_kg,
                    'condition' => $condition,
                    'operational_status' => $condition === 'V' ? 'empty' : 'loaded',
                    'active' => true,
                    'last_updated_date' => now(),
                    'last_updated_by_user_id' => Auth::id(),
                ];

                if ($source === 'carrier') {
                    $updates['carrier_seal'] = $sealValue;
                } elseif ($source === 'shipper') {
                    $updates['shipper_seal'] = $sealValue;
                }

                $container->update($updates);
            } else {
                $container = \App\Models\Container::create([
                    'container_number' => $containerData['container_number'],
                    'container_type_id' => $containerData['container_type_id'],
                    'tare_weight_kg' => $containerData['tare_weight'] ?? null,
                    'max_gross_weight_kg' => null,
                    'current_gross_weight_kg' =>
                        $condition === 'V'
                            ? null
                            : ($containerData['gross_weight_kg'] ?? null),
                    'condition' => $condition,
                    'carrier_seal' => $source === 'carrier' ? $sealValue : null,
                    'shipper_seal' => $source === 'shipper' ? $sealValue : null,
                    'operational_status' => $condition === 'V' ? 'empty' : 'loaded',
                    'active' => true,
                    'created_date' => now(),
                    'created_by_user_id' => Auth::id(),
                ]);
            }

            $preserved = $existingPivot[$container->container_number] ?? [];
            $sourceSeals = $preserved['source_seals'] ?? null;

            if ($source === 'source') {
                $decoded = $this->decodeSourceSeals($sourceSeals);

                if ($decoded === []) {
                    $decoded[] = [
                        'seal_number' => $sealValue,
                        'issuer_code' => null,
                    ];
                } elseif (is_array($decoded[0] ?? null)) {
                    $decoded[0]['seal_number'] = $sealValue;
                } else {
                    $decoded[0] = $sealValue;
                }

                $sourceSeals = json_encode(
                    $decoded,
                    JSON_UNESCAPED_UNICODE
                );
            }

            $shipmentItem->containers()->attach($container->id, [
                'package_quantity' =>
                    $containerData['package_quantity'] ?? null,
                'gross_weight_kg' =>
                    $containerData['gross_weight_kg'] ?? null,
                'net_weight_kg' => $containerData['net_weight_kg'] ?? null,
                'volume_m3' => $containerData['volume_m3'] ?? null,
                'loading_sequence' => $containerData['loading_sequence'] ?? null,
                'source_seals' => $sourceSeals,
                'source_line_numbers' => $preserved['source_line_numbers'] ?? null,
                'verified_gross_mass_kg' => $preserved['verified_gross_mass_kg'] ?? null,
                'container_condition' => $preserved['container_condition'] ?? null,
                'created_date' => now(),
                'created_by_user_id' => Auth::id(),
            ]);
        }
    }
}
