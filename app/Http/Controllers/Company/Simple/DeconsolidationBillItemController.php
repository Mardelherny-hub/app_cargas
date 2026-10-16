<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\BillOfLading;
use App\Models\CargoType;
use App\Models\DeconsolidationOperation;
use App\Traits\UserHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Líneas de mercadería de conocimientos hijos ATA-DESC.
 *
 * Solo guarda borradores en expedientes propios. No genera contenedores,
 * no inventa valores aduaneros y nunca invoca el webservice.
 */
class DeconsolidationBillItemController extends Controller
{
    use UserHelper;

    public function create(int $operation, int $bill): View
    {
        [$company, $operation, $bill] = $this->authorizedBill($operation, $bill);

        $cargoTypes = CargoType::query()
            ->where('active', true)
            ->where('allows_deconsolidation', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('company.simple.desconsolidado.operations.bills.items.create', compact(
            'company', 'operation', 'bill', 'cargoTypes'
        ));
    }

    public function store(Request $request, int $operation, int $bill): RedirectResponse
    {
        [, $operation, $bill] = $this->authorizedBill($operation, $bill);

        foreach ([
            'packaging_code', 'container_condition', 'tariff_position',
            'is_secure_logistics_operator', 'is_monitored_transit', 'is_renar',
        ] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([
                    $field => strtoupper(trim($request->input($field))),
                ]);
            }
        }

        foreach (['item_description', 'cargo_marks'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }

        $data = $request->validate([
            'line_number' => ['required', 'integer', 'between:1,999'],
            'cargo_type_id' => [
                'required', 'integer',
                Rule::exists('cargo_types', 'id')
                    ->where(fn ($query) => $query->where('active', true)
                        ->where('allows_deconsolidation', true)),
            ],
            'packaging_code' => ['required', 'string', 'size:2'],
            'container_condition' => ['required', 'in:H,P'],
            'package_quantity' => ['required', 'integer', 'between:1,999999999'],
            'gross_weight_kg' => [
                'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99',
            ],
            'item_description' => ['required', 'string', 'max:80'],
            'cargo_marks' => ['required', 'string', 'max:100'],
            'tariff_position' => ['required', 'string', 'max:16'],
            'is_secure_logistics_operator' => ['required', 'in:S,N'],
            'is_monitored_transit' => ['required', 'in:S,N'],
            'is_renar' => ['required', 'in:S,N'],
        ], [
            'line_number.required' => 'Ingresá el número de línea del conocimiento.',
            'line_number.between' => 'El número de línea debe estar entre 1 y 999.',
            'cargo_type_id.required' => 'Seleccioná el tipo de carga declarado.',
            'packaging_code.required' => 'Ingresá el código de embalaje ATA-DESC.',
            'packaging_code.size' => 'El código de embalaje ATA-DESC debe tener 2 caracteres.',
            'container_condition.required' => 'Indicá la condición del contenedor (H/P).',
            'container_condition.in' => 'La condición del contenedor debe ser H o P.',
            'package_quantity.required' => 'Ingresá la cantidad real de bultos.',
            'gross_weight_kg.required' => 'Ingresá el peso bruto declarado de esta línea.',
            'gross_weight_kg.decimal' => 'El peso admite hasta dos decimales.',
            'item_description.required' => 'Ingresá la descripción real de la mercadería.',
            'item_description.max' => 'La descripción de cada línea ATA-DESC admite hasta 80 caracteres.',
            'cargo_marks.required' => 'Ingresá las marcas o números de los bultos.',
            'cargo_marks.max' => 'Las marcas de la línea admiten hasta 100 caracteres.',
            'tariff_position.required' => 'Ingresá la posición arancelaria declarada.',
            'is_secure_logistics_operator.required' => 'Confirmá si corresponde operador logístico seguro.',
            'is_monitored_transit.required' => 'Confirmá si corresponde tránsito monitoreado.',
            'is_renar.required' => 'Confirmá si corresponde RENAR.',
        ]);

        // En títulos no consolidados, el contrato real ATA-DESC exige 7..15.
        // En consolidados exige un valor común de hasta 16 caracteres.
        $isConsolidated = (int) $bill->getRawOriginal('is_consolidated') === 1;
        $tariffLength = mb_strlen($data['tariff_position']);
        if (!$isConsolidated && ($tariffLength < 7 || $tariffLength > 15)) {
            throw ValidationException::withMessages([
                'tariff_position' =>
                    'En un conocimiento no consolidado, la posición arancelaria debe tener entre 7 y 15 caracteres.',
            ]);
        }

        [$item, $created] = DB::transaction(function () use ($operation, $bill, $data) {
            // Bloquear la cabecera evita carreras entre dos altas simultáneas.
            // La restricción única (bill_of_lading_id, line_number) es el resguardo de BD.
            $lockedBill = $operation->billsOfLading()
                ->whereNull('shipment_id')
                ->lockForUpdate()
                ->findOrFail($bill->id);
            $this->assertDraftBill($lockedBill, $operation);

            $existing = $lockedBill->shipmentItems()
                ->where('line_number', $data['line_number'])
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            // El XML admite una posición arancelaria y tres indicadores
            // por TÍTULO: todas las líneas deben ser consistentes.
            $commonFields = [
                'tariff_position' => 'posición arancelaria',
                'is_secure_logistics_operator' => 'operador logístico seguro',
                'is_monitored_transit' => 'tránsito monitoreado',
                'is_renar' => 'RENAR',
            ];
            $previousLines = $lockedBill->shipmentItems()
                ->get(array_keys($commonFields));

            foreach ($previousLines as $previous) {
                foreach ($commonFields as $field => $label) {
                    $value = strtoupper(trim((string) $previous->{$field}));
                    if ($value !== '' && $value !== $data[$field]) {
                        throw ValidationException::withMessages([
                            $field => "El dato de {$label} debe coincidir con las otras líneas del conocimiento.",
                        ]);
                    }
                }
            }

            // createQuietly es deliberado: el evento saved() existente
            // recalcula gross_weight_kg, net_weight_kg y container_count
            // incluso cuando aún falta cargar parte de la mercadería.
            // En ATA-DESC conservar la cabecera declarada es indispensable.
            $newItem = $lockedBill->shipmentItems()->createQuietly([
                'line_number' => $data['line_number'],
                'cargo_type_id' => $data['cargo_type_id'],
                'packaging_code' => $data['packaging_code'],
                'container_condition' => $data['container_condition'],
                'package_quantity' => $data['package_quantity'],
                'gross_weight_kg' => $data['gross_weight_kg'],
                'item_description' => $data['item_description'],
                'cargo_marks' => $data['cargo_marks'],
                'tariff_position' => $data['tariff_position'],
                'is_secure_logistics_operator' => $data['is_secure_logistics_operator'],
                'is_monitored_transit' => $data['is_monitored_transit'],
                'is_renar' => $data['is_renar'],
                'status' => 'draft',
                'created_by_user_id' => $this->getCurrentUser()?->id,
                'last_updated_by_user_id' => $this->getCurrentUser()?->id,
            ]);

            return [$newItem, true];
        });

        return redirect()
            ->route('company.simple.desconsolidado.operations.show', [
                'operation' => $operation->id,
            ])
            ->with(
                'success',
                $created
                    ? "Línea {$item->line_number} guardada como borrador. El peso total del conocimiento se conserva. Falta revisar las demás líneas y los contenedores que correspondan."
                    : "La línea {$item->line_number} ya existía. No se creó otra ni se modificaron sus datos."
            );
    }

    public function edit(int $operation, int $bill, int $item): View
    {
        [$company, $operationRecord, $billRecord] = $this->authorizedBill($operation, $bill);
        $line = $billRecord->shipmentItems()->findOrFail($item);
        abort_unless($line->status === 'draft', 409);
        $cargoTypes = CargoType::query()->where('active', true)
            ->where('allows_deconsolidation', true)->orderBy('name')->get(['id', 'name']);
        return view('company.simple.desconsolidado.operations.bills.items.create', [
            'company' => $company, 'operation' => $operationRecord,
            'bill' => $billRecord, 'cargoTypes' => $cargoTypes, 'editingItem' => $line,
        ]);
    }

    public function update(Request $request, int $operation, int $bill, int $item): RedirectResponse
    {
        [, $operationRecord, $billRecord] = $this->authorizedBill($operation, $bill);
        $line = $billRecord->shipmentItems()->findOrFail($item);
        abort_unless($line->status === 'draft', 409);
        $fields = ['line_number', 'cargo_type_id', 'packaging_code', 'container_condition',
            'package_quantity', 'gross_weight_kg', 'item_description', 'cargo_marks',
            'tariff_position', 'is_secure_logistics_operator', 'is_monitored_transit', 'is_renar'];
        foreach ($fields as $field) {
            if (is_string($request->input($field))) {
                $value = trim($request->input($field));
                $request->merge([$field => in_array($field, [
                    'packaging_code', 'container_condition', 'tariff_position',
                    'is_secure_logistics_operator', 'is_monitored_transit', 'is_renar',
                ], true) ? strtoupper($value) : $value]);
            }
        }
        $data = $request->validate([
            'line_number' => ['required', 'integer', 'between:1,999'],
            'cargo_type_id' => ['required', 'integer', Rule::exists('cargo_types', 'id')
                ->where(fn ($query) => $query->where('active', true)->where('allows_deconsolidation', true))],
            'packaging_code' => ['required', 'string', 'size:2'],
            'container_condition' => ['required', 'in:H,P'],
            'package_quantity' => ['required', 'integer', 'between:1,999999999'],
            'gross_weight_kg' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'item_description' => ['required', 'string', 'max:80'],
            'cargo_marks' => ['required', 'string', 'max:100'],
            'tariff_position' => ['required', 'string', 'max:16'],
            'is_secure_logistics_operator' => ['required', 'in:S,N'],
            'is_monitored_transit' => ['required', 'in:S,N'],
            'is_renar' => ['required', 'in:S,N'],
        ]);
        $len = mb_strlen($data['tariff_position']);
        if ((int) $billRecord->getRawOriginal('is_consolidated') !== 1 && ($len < 7 || $len > 15)) {
            throw ValidationException::withMessages(['tariff_position' => 'La posición arancelaria debe tener entre 7 y 15 caracteres.']);
        }
        DB::transaction(function () use ($operationRecord, $billRecord, $item, $data) {
            $lockedBill = $operationRecord->billsOfLading()->whereNull('shipment_id')
                ->lockForUpdate()->findOrFail($billRecord->id);
            $this->assertDraftBill($lockedBill, $operationRecord);
            $lockedLine = $lockedBill->shipmentItems()->lockForUpdate()->findOrFail($item);
            abort_unless($lockedLine->status === 'draft', 409);
            abort_if($lockedBill->shipmentItems()->whereKeyNot($item)
                ->where('line_number', $data['line_number'])->exists(), 422,
                'El número de línea ya existe en este conocimiento.');
            foreach (['tariff_position', 'is_secure_logistics_operator',
                'is_monitored_transit', 'is_renar'] as $field) {
                abort_if($lockedBill->shipmentItems()->whereKeyNot($item)
                    ->where($field, '!=', $data[$field])->exists(), 422,
                    'Los indicadores y la posición arancelaria deben coincidir entre líneas.');
            }
            abort_if($lockedLine->containers()->exists() && (
                $data['packaging_code'] !== $lockedLine->packaging_code ||
                $data['container_condition'] !== $lockedLine->container_condition), 409,
                'Desvinculá primero los contenedores antes de cambiar embalaje o condición.');
            $lockedLine->fill($data);
            $lockedLine->last_updated_by_user_id = $this->getCurrentUser()?->id;
            $lockedLine->saveQuietly();
        });
        return redirect()->route('company.simple.desconsolidado.operations.show', [
            'operation' => $operation,
        ])->with('success', 'Línea borrador actualizada.');
    }

    /** Elimina una línea local en borrador, sin borrar fichas globales. */
    public function destroy(int $operation, int $bill, int $item): RedirectResponse
    {
        [, $operationRecord, $billRecord] = $this->authorizedBill($operation, $bill);

        DB::transaction(function () use ($operationRecord, $billRecord, $item) {
            $lockedBill = $operationRecord->billsOfLading()
                ->whereNull('shipment_id')->lockForUpdate()
                ->findOrFail($billRecord->id);
            $this->assertDraftBill($lockedBill, $operationRecord);

            $line = $lockedBill->shipmentItems()->lockForUpdate()->findOrFail($item);
            abort_unless($line->status === 'draft', 409, 'La línea ya no es un borrador.');

            $line->containers()->detach();
            $line->deleteQuietly();
        });

        return redirect()->route('company.simple.desconsolidado.operations.show', [
            'operation' => $operation,
        ])->with('success', 'Línea borrador eliminada; los contenedores del catálogo se conservaron.');
    }

    /**
     * El acceso depende del expediente autenticado, nunca de un BL global.
     *
     * @return array{0:\App\Models\Company,1:DeconsolidationOperation,2:BillOfLading}
     */
    private function authorizedBill(int $operationId, int $billId): array
    {
        $company = $this->getUserCompany();

        abort_unless($company, 403, 'No se encontró una empresa asociada al usuario.');
        abort_unless(
            $this->hasCompanyRole('Desconsolidador'),
            403,
            'La empresa no tiene habilitado el rol Desconsolidador.'
        );

        $operation = DeconsolidationOperation::query()
            ->forCompany((int) $company->id)
            ->findOrFail($operationId);

        $bill = $operation->billsOfLading()
            ->whereNull('shipment_id')
            ->findOrFail($billId);

        $this->assertDraftBill($bill, $operation);

        return [$company, $operation, $bill];
    }

    private function assertDraftBill(
        BillOfLading $bill,
        DeconsolidationOperation $operation
    ): void {
        abort_unless(
            $bill->status === 'draft'
            && $bill->canBeEdited()
            && $bill->is_house_bill
            && (string) $bill->master_bill_number === (string) $operation->master_bill_number
            && (string) data_get(
                $bill->webservice_data,
                'argentina.desconsolidated.master_loading_port_code'
            ) === (string) $operation->master_loading_port_code
            && !in_array($bill->argentina_status, ['sent', 'approved'], true)
            && !in_array($bill->webservice_status, ['sent', 'processing', 'approved', 'accepted'], true),
            409,
            'El conocimiento no es un borrador editable o no coincide con su título madre.'
        );
    }
}
