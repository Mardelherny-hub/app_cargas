<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\BillOfLading;
use App\Models\CargoType;
use App\Models\DeconsolidationOperation;
use App\Models\Port;
use App\Traits\UserHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Alta segura de conocimientos hijos ATA-DESC.
 *
 * No usa el alta general de Cargas: un BL de una desconsolidadora pertenece
 * a su expediente y no requiere Shipment, Voyage ni una embarcación ficticia.
 * Este controlador sólo guarda borradores; no transmite a Aduana.
 */
class DeconsolidationBillController extends Controller
{
    use UserHelper;

    public function create(int $operation): View
    {
        [$company, $operation] = $this->authorizedOperation($operation);

        $cargoTypes = CargoType::query()
            ->where('active', true)
            ->where('allows_deconsolidation', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view(
            'company.simple.desconsolidado.operations.bills.create',
            compact('company', 'operation', 'cargoTypes')
        );
    }

    public function store(Request $request, int $operation): RedirectResponse
    {
        [, $operation] = $this->authorizedOperation($operation);

        foreach (['bill_number', 'cargo_description', 'cargo_marks',
            'origin_location', 'origin_country_code',
            'is_consolidated', 'is_transit_transshipment'] as $field) {
            if (is_string($request->input($field))) {
                $value = trim($request->input($field));
                $request->merge([
                    $field => in_array($field, [
                        'is_consolidated', 'is_transit_transshipment',
                        'origin_country_code',
                    ], true) ? strtoupper($value) : $value,
                ]);
            }
        }

        $activeClient = Rule::exists('clients', 'id')
            ->where(fn ($query) => $query->where('status', 'active')
                ->whereNotNull('verified_at'));
        $activePort = Rule::exists('ports', 'id')
            ->where(fn ($query) => $query->where('active', true));

        $data = $request->validate([
            'bill_number' => ['required', 'string', 'max:18'],
            'loading_date' => ['nullable', 'date_format:Y-m-d'],
            'origin_loading_date' => ['nullable', 'date_format:Y-m-d'],
            'discharge_date' => ['nullable', 'date_format:Y-m-d'],
            'origin_location' => ['nullable', 'string', 'max:50'],
            'origin_country_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{3}$/'],
            'shipper_id' => ['required', 'integer', $activeClient],
            'consignee_id' => ['required', 'integer', $activeClient],
            'loading_port_id' => ['required', 'integer', $activePort],
            'discharge_port_id' => ['required', 'integer', $activePort],
            'primary_cargo_type_id' => [
                'required', 'integer',
                Rule::exists('cargo_types', 'id')
                    ->where(fn ($query) => $query->where('active', true)
                        ->where('allows_deconsolidation', true)),
            ],
            'gross_weight_kg' => [
                'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99',
            ],
            // Descripción general del BL (TEXT en BD). El límite de 80
            // caracteres corresponde a cada línea ATA-DESC, no a este campo.
            'cargo_description' => ['required', 'string'],
            'cargo_marks' => ['required', 'string', 'max:80'],
            'is_consolidated' => ['required', 'in:S,N'],
            'is_transit_transshipment' => ['required', 'in:S,N'],
        ], [
            'bill_number.required' => 'Ingresá el número del conocimiento hijo según la documentación.',
            'bill_number.max' => 'El número del conocimiento hijo admite hasta 18 caracteres.',
            'loading_date.date_format' => 'Ingresá una fecha de embarque válida.',
            'origin_loading_date.date_format' => 'Ingresá una fecha válida de carga en origen.',
            'discharge_date.date_format' => 'Ingresá una fecha de descarga válida.',
            'origin_country_code.regex' => 'El código AFIP del país de origen debe tener 3 letras o números.',
            'shipper_id.required' => 'Seleccioná el cargador del conocimiento hijo.',
            'consignee_id.required' => 'Seleccioná el consignatario del conocimiento hijo.',
            'loading_port_id.required' => 'Seleccioná el puerto de embarque del conocimiento hijo.',
            'discharge_port_id.required' => 'Seleccioná el puerto de descarga del conocimiento hijo.',
            'primary_cargo_type_id.required' => 'Seleccioná el tipo de carga indicado en el conocimiento.',
            'gross_weight_kg.required' => 'Ingresá el peso bruto declarado.',
            'gross_weight_kg.decimal' => 'El peso admite como máximo dos decimales.',
            'cargo_description.required' => 'Ingresá la descripción de la mercadería según la documentación.',
            'cargo_marks.required' => 'Ingresá las marcas de los bultos.',
            'is_consolidated.required' => 'Indicá si el conocimiento es consolidado.',
            'is_transit_transshipment.required' => 'Indicá si corresponde tránsito o transbordo.',
        ]);

        // ATA-DESC exige descarga en Argentina. Nunca inferir país o puerto
        // a partir del viaje de otra empresa ni inventar códigos de destino.
        $destination = Port::query()->with('country')->find($data['discharge_port_id']);
        if (!$destination || strtoupper(trim((string) $destination->country?->alpha2_code)) !== 'AR') {
            throw ValidationException::withMessages([
                'discharge_port_id' =>
                    'El puerto de descarga de ATA-DESC debe estar ubicado en Argentina.',
            ]);
        }

        // El código AFIP ya está en el país asociado al puerto real.
        // No pedirlo nuevamente ni aceptar un código falsificado por POST.
        $afipCountry = strtoupper(trim((string) $destination->country?->codigo_afip));
        if (!preg_match('/^[A-Z0-9]{3}$/', $afipCountry)) {
            throw ValidationException::withMessages([
                'discharge_port_id' =>
                    'El puerto seleccionado no tiene un código de país AFIP válido configurado. Consultá al administrador.',
            ]);
        }

        // No reactivar registros archivados ni cambiar silenciosamente
        // un conocimiento existente en un doble envío del formulario.
        $existing = $operation->billsOfLading()
            ->withTrashed()
            ->where('bill_number', $data['bill_number'])
            ->first();

        if ($existing?->trashed()) {
            throw ValidationException::withMessages([
                'bill_number' =>
                    'Este conocimiento figura como archivado en el expediente. Revisá su estado antes de continuar.',
            ]);
        }

        // Todos estos valores provienen de datos declarados o del expediente
        // autenticado; NO aceptar shipment_id, operation_id ni empresa por POST.
        $attributes = [
            'house_bill_number' => $data['bill_number'],
            'master_bill_number' => $operation->master_bill_number,
            'loading_date' => $data['loading_date'] ?? null,
            'origin_loading_date' => $data['origin_loading_date'] ?? null,
            'discharge_date' => $data['discharge_date'] ?? null,
            'origin_location' => $data['origin_location'] ?? null,
            'origin_country_code' => $data['origin_country_code'] ?? null,
            'shipper_id' => $data['shipper_id'],
            'consignee_id' => $data['consignee_id'],
            'loading_port_id' => $data['loading_port_id'],
            'discharge_port_id' => $data['discharge_port_id'],
            'primary_cargo_type_id' => $data['primary_cargo_type_id'],
            'gross_weight_kg' => $data['gross_weight_kg'],
            'cargo_description' => $data['cargo_description'],
            'cargo_marks' => $data['cargo_marks'],
            'destination_country_code' => $afipCountry,
            'is_house_bill' => true,
            // El modelo actual convierte este campo a string y la columna es
            // booleana: guardar 1/0 explícitos mantiene S/N en el XML.
            'is_consolidated' => $data['is_consolidated'] === 'S' ? 1 : 0,
            'is_transit_transshipment' => $data['is_transit_transshipment'],
            'status' => 'draft',
            'created_by_user_id' => $this->getCurrentUser()?->id,
            'last_updated_by_user_id' => $this->getCurrentUser()?->id,
            'webservice_data' => [
                'argentina' => [
                    'desconsolidated' => [
                        'master_loading_port_code' =>
                            $operation->master_loading_port_code,
                    ],
                ],
            ],
        ];

        // El vínculo con el expediente se establece por la relación.
        // El índice único en BD protege de altas concurrentes repetidas.
        $bill = $operation->billsOfLading()->firstOrCreate(
            ['bill_number' => $data['bill_number']],
            $attributes
        );

        return redirect()
            ->route('company.simple.desconsolidado.operations.show', [
                'operation' => $operation->id,
            ])
            ->with(
                'success',
                $bill->wasRecentlyCreated
                    ? 'Conocimiento hijo guardado como borrador. Completá sus líneas de mercadería antes de transmitir.'
                    : 'El conocimiento hijo ya estaba registrado. Se conservaron los datos existentes.'
            );
    }

    public function edit(int $operation, int $bill): View
    {
        [$company, $operationRecord] = $this->authorizedOperation($operation);
        $record = $operationRecord->billsOfLading()->whereNull('shipment_id')->findOrFail($bill);
        $this->assertDraftHouseBill($record, $operationRecord);
        $cargoTypes = CargoType::query()->where('active', true)
            ->where('allows_deconsolidation', true)->orderBy('name')->get(['id', 'name']);

        return view('company.simple.desconsolidado.operations.bills.create', [
            'company' => $company, 'operation' => $operationRecord,
            'cargoTypes' => $cargoTypes, 'editingBill' => $record,
        ]);
    }

    public function update(Request $request, int $operation, int $bill): RedirectResponse
    {
        [, $operationRecord] = $this->authorizedOperation($operation);
        $fields = ['bill_number', 'loading_date', 'origin_loading_date', 'discharge_date',
            'origin_location', 'origin_country_code', 'shipper_id', 'consignee_id', 'loading_port_id',
            'discharge_port_id', 'primary_cargo_type_id', 'gross_weight_kg',
            'cargo_description', 'cargo_marks', 'is_consolidated', 'is_transit_transshipment'];
        foreach ($fields as $field) {
            if (is_string($request->input($field))) {
                $value = trim($request->input($field));
                $request->merge([$field => in_array($field, [
                    'is_consolidated', 'is_transit_transshipment',
                    'origin_country_code',
                ], true) ? strtoupper($value) : $value]);
            }
        }
        $activeClient = Rule::exists('clients', 'id')->where(fn ($query) =>
            $query->where('status', 'active')->whereNotNull('verified_at'));
        $activePort = Rule::exists('ports', 'id')->where(fn ($query) =>
            $query->where('active', true));
        $data = $request->validate([
            'bill_number' => ['required', 'string', 'max:18'],
            'loading_date' => ['nullable', 'date_format:Y-m-d'],
            'origin_loading_date' => ['nullable', 'date_format:Y-m-d'],
            'discharge_date' => ['nullable', 'date_format:Y-m-d'],
            'origin_location' => ['nullable', 'string', 'max:50'],
            'origin_country_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{3}$/'],
            'shipper_id' => ['required', 'integer', $activeClient],
            'consignee_id' => ['required', 'integer', $activeClient],
            'loading_port_id' => ['required', 'integer', $activePort],
            'discharge_port_id' => ['required', 'integer', $activePort],
            'primary_cargo_type_id' => ['required', 'integer', Rule::exists('cargo_types', 'id')
                ->where(fn ($query) => $query->where('active', true)->where('allows_deconsolidation', true))],
            'gross_weight_kg' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'cargo_description' => ['required', 'string'],
            'cargo_marks' => ['required', 'string', 'max:80'],
            'is_consolidated' => ['required', 'in:S,N'],
            'is_transit_transshipment' => ['required', 'in:S,N'],
        ]);
        $destination = Port::query()->with('country')->find($data['discharge_port_id']);
        if (!$destination || strtoupper(trim((string) $destination->country?->alpha2_code)) !== 'AR'
            || !preg_match('/^[A-Z0-9]{3}$/', strtoupper(trim((string) $destination->country?->codigo_afip)))) {
            throw ValidationException::withMessages([
                'discharge_port_id' => 'El puerto de descarga debe ser argentino y tener un código de país AFIP válido. Consultá al administrador si faltan datos en el catálogo.',
            ]);
        }
        DB::transaction(function () use ($operationRecord, $bill, $data, $destination) {
            $record = $operationRecord->billsOfLading()->whereNull('shipment_id')
                ->lockForUpdate()->findOrFail($bill);
            $this->assertDraftHouseBill($record, $operationRecord);
            abort_if($operationRecord->billsOfLading()->withTrashed()
                ->whereKeyNot($record->id)->where('bill_number', $data['bill_number'])->exists(),
                409, 'Ya hay otro conocimiento con ese número en este expediente.');
            abort_if($record->shipmentItems()->exists() && (
                $data['bill_number'] !== $record->bill_number
                || $data['is_consolidated'] !== ((int) $record->getRawOriginal('is_consolidated') === 1 ? 'S' : 'N')),
                409, 'El número y la condición de consolidado se mantienen mientras este conocimiento tenga líneas de mercadería.');
            $record->fill(array_merge($data, [
                'is_consolidated' => $data['is_consolidated'] === 'S' ? 1 : 0,
                'destination_country_code' => strtoupper(trim((string) $destination->country->codigo_afip)),
            ]));
            $record->last_updated_by_user_id = $this->getCurrentUser()?->id;
            $record->save();
        });
        return redirect()->route('company.simple.desconsolidado.operations.show', [
            'operation' => $operation,
        ])->with('success', 'Cambios del conocimiento hijo guardados correctamente.');
    }

    private function assertDraftHouseBill(BillOfLading $bill, DeconsolidationOperation $operation): void
    {
        abort_unless($bill->status === 'draft' && $bill->canBeEdited()
            && $bill->is_house_bill
            && (string) $bill->master_bill_number === (string) $operation->master_bill_number
            && (string) data_get($bill->webservice_data,
                'argentina.desconsolidated.master_loading_port_code') === (string) $operation->master_loading_port_code
            && !in_array($bill->argentina_status, ['sent', 'approved'], true)
            && !in_array($bill->webservice_status, ['sent', 'processing', 'approved', 'accepted'], true),
            409, 'Este conocimiento no está disponible para edición en su estado actual.');
    }

    /** Quitar un título hijo local solamente cuando no haya sido transmitido. */
    public function destroy(int $operation, int $bill): RedirectResponse
    {
        [, $operationRecord] = $this->authorizedOperation($operation);

        DB::transaction(function () use ($operationRecord, $bill) {
            $record = $operationRecord->billsOfLading()
                ->whereNull('shipment_id')->lockForUpdate()->findOrFail($bill);

            abort_unless(
                $record->status === 'draft' && $record->canBeEdited()
                && $record->is_house_bill
                && (string) $record->master_bill_number === (string) $operationRecord->master_bill_number
                && !in_array($record->argentina_status, ['sent', 'approved'], true)
                && !in_array($record->webservice_status, ['sent', 'processing', 'approved', 'accepted'], true),
                409, 'Este conocimiento no está disponible para edición en su estado actual.'
            );
            abort_if($record->shipmentItems()->exists(), 409,
                'Este conocimiento tiene líneas de mercadería. Para archivarlo, primero deben eliminarse sus líneas en borrador.');

            $record->delete(); // SoftDeletes: conservar trazabilidad.
        });

        return redirect()->route('company.simple.desconsolidado.operations.show', [
            'operation' => $operation,
        ])->with('success', 'Conocimiento hijo archivado. Su registro se conserva en el historial.');
    }

    /**
     * Usar siempre las dos condiciones: rol y propiedad del expediente.
     *
     * @return array{0:\App\Models\Company,1:DeconsolidationOperation}
     */
    private function authorizedOperation(int $operationId): array
    {
        $company = $this->getUserCompany();

        abort_unless($company, 403, 'Tu usuario no tiene una empresa asociada. Consultá al administrador.');
        abort_unless(
            $this->hasCompanyRole('Desconsolidador'),
            403,
            'La empresa no está habilitada para operar Desconsolidados. Consultá al administrador.'
        );

        $operation = DeconsolidationOperation::query()
            ->forCompany((int) $company->id)
            ->findOrFail($operationId);

        return [$company, $operation];
    }
}
