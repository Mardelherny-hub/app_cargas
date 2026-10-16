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
            'is_consolidated', 'is_transit_transshipment'] as $field) {
            if (is_string($request->input($field))) {
                $value = trim($request->input($field));
                $request->merge([
                    $field => in_array($field, [
                        'is_consolidated', 'is_transit_transshipment',
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
            'bill_number.required' => 'Ingresá el número real del conocimiento hijo.',
            'bill_number.max' => 'El número del conocimiento hijo admite hasta 18 caracteres.',
            'shipper_id.required' => 'Seleccioná el cargador del conocimiento hijo.',
            'consignee_id.required' => 'Seleccioná el consignatario del conocimiento hijo.',
            'loading_port_id.required' => 'Seleccioná el puerto de embarque del conocimiento hijo.',
            'discharge_port_id.required' => 'Seleccioná el puerto de descarga del conocimiento hijo.',
            'primary_cargo_type_id.required' => 'Seleccioná el tipo de carga real.',
            'gross_weight_kg.required' => 'Ingresá el peso bruto declarado.',
            'gross_weight_kg.decimal' => 'El peso admite como máximo dos decimales.',
            'cargo_description.required' => 'Ingresá la descripción real de la mercadería.',
            'cargo_marks.required' => 'Ingresá las marcas de los bultos.',
            'is_consolidated.required' => 'Indicá si el conocimiento es consolidado (S/N).',
            'is_transit_transshipment.required' => 'Indicá si corresponde tránsito o transbordo (S/N).',
        ]);

        // ATA-DESC exige descarga en Argentina. Nunca inferir país o puerto
        // a partir del viaje de otra empresa ni inventar códigos de destino.
        $destination = Port::query()->with('country')->find($data['discharge_port_id']);
        if (!$destination || strtoupper(trim((string) $destination->country?->alpha2_code)) !== 'AR') {
            throw ValidationException::withMessages([
                'discharge_port_id' =>
                    'Para ATA-DESC el puerto de descarga debe pertenecer a Argentina.',
            ]);
        }

        // El código AFIP ya está en el país asociado al puerto real.
        // No pedirlo nuevamente ni aceptar un código falsificado por POST.
        $afipCountry = strtoupper(trim((string) $destination->country?->codigo_afip));
        if (!preg_match('/^[A-Z0-9]{3}$/', $afipCountry)) {
            throw ValidationException::withMessages([
                'discharge_port_id' =>
                    'El puerto seleccionado no tiene un código AFIP de país válido en el catálogo.',
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
                    'Ese conocimiento ya existe archivado en esta operación. Revisalo antes de volver a cargarlo.',
            ]);
        }

        // Todos estos valores provienen de datos declarados o del expediente
        // autenticado; NO aceptar shipment_id, operation_id ni empresa por POST.
        $attributes = [
            'house_bill_number' => $data['bill_number'],
            'master_bill_number' => $operation->master_bill_number,
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
                    ? 'Conocimiento hijo guardado como borrador. Falta cargar sus líneas de mercadería antes de transmitir.'
                    : 'El conocimiento hijo ya estaba guardado. No se creó otro ni se modificaron sus datos.'
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
        $fields = ['bill_number', 'shipper_id', 'consignee_id', 'loading_port_id',
            'discharge_port_id', 'primary_cargo_type_id', 'gross_weight_kg',
            'cargo_description', 'cargo_marks', 'is_consolidated', 'is_transit_transshipment'];
        foreach ($fields as $field) {
            if (is_string($request->input($field))) {
                $value = trim($request->input($field));
                $request->merge([$field => in_array($field, [
                    'is_consolidated', 'is_transit_transshipment',
                ], true) ? strtoupper($value) : $value]);
            }
        }
        $activeClient = Rule::exists('clients', 'id')->where(fn ($query) =>
            $query->where('status', 'active')->whereNotNull('verified_at'));
        $activePort = Rule::exists('ports', 'id')->where(fn ($query) =>
            $query->where('active', true));
        $data = $request->validate([
            'bill_number' => ['required', 'string', 'max:18'],
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
                'discharge_port_id' => 'El puerto argentino seleccionado debe tener un código de país AFIP válido.',
            ]);
        }
        DB::transaction(function () use ($operationRecord, $bill, $data, $destination) {
            $record = $operationRecord->billsOfLading()->whereNull('shipment_id')
                ->lockForUpdate()->findOrFail($bill);
            $this->assertDraftHouseBill($record, $operationRecord);
            abort_if($operationRecord->billsOfLading()->withTrashed()
                ->whereKeyNot($record->id)->where('bill_number', $data['bill_number'])->exists(),
                409, 'Ya existe ese número de conocimiento en el expediente.');
            abort_if($record->shipmentItems()->exists() && (
                $data['bill_number'] !== $record->bill_number
                || $data['is_consolidated'] !== ((int) $record->getRawOriginal('is_consolidated') === 1 ? 'S' : 'N')),
                409, 'No se puede cambiar el número ni la consolidación con líneas asociadas.');
            $record->fill(array_merge($data, [
                'is_consolidated' => $data['is_consolidated'] === 'S' ? 1 : 0,
                'destination_country_code' => strtoupper(trim((string) $destination->country->codigo_afip)),
            ]));
            $record->last_updated_by_user_id = $this->getCurrentUser()?->id;
            $record->save();
        });
        return redirect()->route('company.simple.desconsolidado.operations.show', [
            'operation' => $operation,
        ])->with('success', 'Conocimiento hijo borrador actualizado.');
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
            409, 'El conocimiento no es un borrador editable.');
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
                409, 'El conocimiento no es un borrador editable.'
            );
            abort_if($record->shipmentItems()->exists(), 409,
                'El conocimiento tiene líneas. Eliminá primero sus líneas borrador.');

            $record->delete(); // SoftDeletes: conservar trazabilidad.
        });

        return redirect()->route('company.simple.desconsolidado.operations.show', [
            'operation' => $operation,
        ])->with('success', 'Conocimiento hijo borrador archivado.');
    }

    /**
     * Usar siempre las dos condiciones: rol y propiedad del expediente.
     *
     * @return array{0:\App\Models\Company,1:DeconsolidationOperation}
     */
    private function authorizedOperation(int $operationId): array
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

        return [$company, $operation];
    }
}
