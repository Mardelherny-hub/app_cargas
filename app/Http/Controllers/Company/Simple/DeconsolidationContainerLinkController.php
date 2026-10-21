<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\DeconsolidationOperation;
use App\Models\ShipmentItem;
use App\Traits\UserHelper;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Vinculación ATA-DESC de contenedores con líneas de mercadería.
 *
 * El catálogo físico es global: se reutilizan contenedores existentes sin
 * modificarlos y se registran los nuevos solo con datos declarados.
 * Nunca se inventan cantidades/pesos del pivote ni se transmite a Aduana.
 */
class DeconsolidationContainerLinkController extends Controller
{
    use UserHelper;

    public function create(int $operation, int $bill, int $item): View
    {
        [$company, $operation, $bill, $item] = $this->authorizedItem(
            $operation, $bill, $item
        );

        // Solo los contenedores asociados a ESTA línea; nunca precargar
        // todo el catálogo global ni consultar otras empresas.
        $linkedContainers = $item->containers()
            ->orderBy('containers.container_number')
            ->get(['containers.id', 'containers.container_number']);

        return view(
            'company.simple.desconsolidado.operations.bills.items.containers.create',
            compact('company', 'operation', 'bill', 'item', 'linkedContainers')
        );
    }

    public function store(
        Request $request,
        int $operation,
        int $bill,
        int $item
    ): RedirectResponse {
        [, $operation, $bill, $item] = $this->authorizedItem(
            $operation, $bill, $item
        );

        if (is_string($request->input('container_number'))) {
            $request->merge([
                'container_number' => strtoupper(
                    trim($request->input('container_number'))
                ),
            ]);
        }

        $data = $request->validate([
            'container_number' => ['required', 'string', 'max:15'],
        ], [
            'container_number.required' =>
                'Ingresá el número del contenedor que querés vincular a esta línea.',
            'container_number.max' =>
                'El número del contenedor admite hasta 15 caracteres.',
        ]);

        [$container, $linked] = DB::transaction(function () use (
            $operation, $bill, $item, $data
        ) {
            // Bloquear la cabecera del BL para serializar vinculaciones
            // simultáneas en esta operación. No se modifica la cabecera.
            $lockedBill = $operation->billsOfLading()
                ->whereNull('shipment_id')
                ->lockForUpdate()
                ->findOrFail($bill->id);

            $this->assertEditableBill($lockedBill, $operation);

            $lockedItem = $lockedBill->shipmentItems()
                ->lockForUpdate()
                ->findOrFail($item->id);

            $this->assertContainerizedDraft($lockedItem);

            // El contenedor físico es compartido: solo búsqueda exacta.
            // Nunca se crea ni se sobrescribe un contenedor global.
            $container = Container::query()
                ->where('container_number', $data['container_number'])
                ->where('active', true)
                ->where('blocked', false)
                ->first();

            if (!$container) {
                throw ValidationException::withMessages([
                    'container_number' =>
                        'No se encontró un contenedor activo y habilitado con ese número. '
                        . 'Revisá el número o su estado en el catálogo. Si no está registrado, '
                        . 'podés utilizar «Registrar contenedor nuevo».',
                ]);
            }

            if ($lockedItem->containers()
                ->where('containers.id', $container->id)
                ->exists()
            ) {
                return [$container, false];
            }

            // Un contenedor puede corresponder a varias líneas y BLs,
            // pero dentro del MISMO título ATA-DESC no admite H y P
            // contradictorios (contrato del generador XML).
            $otherLines = $lockedBill->shipmentItems()
                ->whereKeyNot($lockedItem->id)
                ->whereHas('containers', fn ($query) =>
                    $query->where('containers.id', $container->id)
                )
                ->get(['id', 'container_condition']);

            foreach ($otherLines as $otherLine) {
                if (strtoupper(trim((string) $otherLine->container_condition))
                    !== $lockedItem->container_condition) {
                    throw ValidationException::withMessages([
                        'container_number' =>
                            'Este contenedor ya está vinculado a otra línea '
                            . 'del mismo conocimiento con una condición H/P diferente. '
                            . 'Revisá las condiciones antes de continuar.',
                    ]);
                }
            }

            // Cantidad, peso bruto, peso neto y volumen del pivote quedan
            // NULL: no atribuir la totalidad de la línea a un contenedor
            // sin un documento que pruebe esa distribución.
            $lockedItem->containers()->attach($container->id, [
                'container_condition' => $lockedItem->container_condition,
                'created_by_user_id' => $this->getCurrentUser()?->id,
            ]);

            return [$container, true];
        });

        return redirect()
            ->route('company.simple.desconsolidado.operations.show', [
                'operation' => $operation->id,
            ])
            ->with(
                'success',
                $linked
                    ? "Contenedor {$container->container_number} vinculado "
                        . "a la línea {$item->line_number} como borrador. "
                        . 'La vinculación no asigna pesos ni cantidades por contenedor. '
                        . 'Revisá su documentación antes de transmitir.'
                    : "El contenedor {$container->container_number} ya estaba "
                        . "vinculado a la línea {$item->line_number}. "
                        . 'Se conserva la vinculación y su ficha original.'
            );
    }

    /** Desvincula únicamente de esta línea; nunca elimina el contenedor global. */
    public function destroy(int $operation, int $bill, int $item, int $container): RedirectResponse
    {
        [, $operationRecord, $billRecord, $itemRecord] = $this->authorizedItem(
            $operation, $bill, $item
        );

        DB::transaction(function () use ($operationRecord, $billRecord, $itemRecord, $container) {
            $lockedBill = $operationRecord->billsOfLading()
                ->whereNull('shipment_id')->lockForUpdate()->findOrFail($billRecord->id);
            $this->assertEditableBill($lockedBill, $operationRecord);
            $lockedItem = $lockedBill->shipmentItems()
                ->lockForUpdate()->findOrFail($itemRecord->id);
            $this->assertContainerizedDraft($lockedItem);
            abort_unless($lockedItem->containers()->where('containers.id', $container)->exists(), 404);
            $lockedItem->containers()->detach($container);
        });

        return redirect()->route('company.simple.desconsolidado.operations.show', [
            'operation' => $operation,
        ])->with('success', 'Contenedor desvinculado de la línea. Sus datos permanecen registrados en el catálogo.');
    }

    /** Actualiza documentación CSC/ACEP solo en contenedores exclusivos del expediente. */
    public function updateCustoms(
        Request $request,
        int $operation,
        int $bill,
        int $item,
        int $container
    ): RedirectResponse {
        [, $operationRecord, $billRecord, $itemRecord] = $this->authorizedItem(
            $operation, $bill, $item
        );

        if (is_string($request->input('acep'))) {
            $value = trim($request->input('acep'));
            $request->merge(['acep' => $value === '' ? null : $value]);
        }

        $data = $request->validate([
            'csc_expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'acep' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
        ], [
            'csc_expiry_date.date_format' => 'Ingresá una fecha de vencimiento CSC válida.',
            'acep.regex' => 'El código ACEP admite únicamente letras y números.',
            'acep.max' => 'El código ACEP admite hasta 20 caracteres.',
        ]);

        if (!empty($data['csc_expiry_date']) && !empty($data['acep'])) {
            throw ValidationException::withMessages([
                'container_customs' => 'Informá la fecha CSC o el código ACEP, pero no ambos simultáneamente.',
            ]);
        }

        DB::transaction(function () use (
            $operationRecord, $billRecord, $itemRecord, $container, $data
        ) {
            $lockedBill = $operationRecord->billsOfLading()
                ->whereNull('shipment_id')->lockForUpdate()->findOrFail($billRecord->id);
            $this->assertEditableBill($lockedBill, $operationRecord);

            $lockedItem = $lockedBill->shipmentItems()
                ->lockForUpdate()->findOrFail($itemRecord->id);
            $this->assertContainerizedDraft($lockedItem);
            abort_unless(
                $lockedItem->containers()->where('containers.id', $container)->exists(),
                404
            );

            $record = Container::query()->lockForUpdate()->findOrFail($container);

            // El catálogo no registra empresa propietaria: limitar esta edición
            // excepcional al usuario creador del contenedor.
            abort_unless(
                $record->created_by_user_id !== null
                && (int) $record->created_by_user_id === (int) $this->getCurrentUser()?->id,
                403,
                'La documentación de este contenedor debe revisarse con su responsable o con el administrador.'
            );

            // Los contenedores son globales: no alterar documentos si también
            // están vinculados a otro expediente o a un viaje transportista.
            $sharedOutsideOperation = $this->containerHasOtherUses(
                (int) $record->id, (int) $operationRecord->id
            );

            if ($sharedOutsideOperation) {
                throw ValidationException::withMessages([
                    'container_customs' => 'Este contenedor está asociado a otros expedientes o viajes. '
                        . 'Su documentación compartida debe revisarse con el administrador.',
                ]);
            }

            $record->update([
                'csc_expiry_date' => $data['csc_expiry_date'] ?? null,
                'acep' => $data['acep'] ?? null,
                'last_updated_date' => now(),
                'last_updated_by_user_id' => $this->getCurrentUser()?->id,
            ]);
        });

        return redirect()
            ->route('company.simple.desconsolidado.operations.show', [
                'operation' => $operationRecord->id,
            ])
            ->with('success', 'Documentación CSC/ACEP del contenedor actualizada. '
                . 'Podés ejecutar nuevamente la validación sin transmitir.');
    }

    /**
     * Formulario de alta excepcional cuando el número todavía no existe
     * en el catálogo global. No modifica contenedores ya registrados.
     */
    public function register(int $operation, int $bill, int $item): View
    {
        [$company, $operation, $bill, $item] = $this->authorizedItem(
            $operation, $bill, $item
        );

        $containerTypes = ContainerType::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view(
            'company.simple.desconsolidado.operations.bills.items.containers.register',
            compact('company', 'operation', 'bill', 'item', 'containerTypes')
        );
    }

    /**
     * Crea el contenedor físico nuevo y su vínculo a la línea en una sola
     * transacción. Se exige estado operativo declarado expresamente para
     * no guardar los valores V/available predeterminados como hechos reales.
     */
    public function storeNew(
        Request $request,
        int $operation,
        int $bill,
        int $item
    ): RedirectResponse {
        [, $operation, $bill, $item] = $this->authorizedItem(
            $operation, $bill, $item
        );

        foreach (['container_number', 'argentina_container_code'] as $field) {
            if (is_string($request->input($field))) {
                $value = strtoupper(trim($request->input($field)));
                $request->merge([
                    $field => $field === 'container_number' || $value !== ''
                        ? $value
                        : null,
                ]);
            }
        }

        foreach (['acep', 'shipper_seal'] as $field) {
            if (is_string($request->input($field))) {
                $value = trim($request->input($field));
                $request->merge([$field => $value === '' ? null : $value]);
            }
        }

        $data = $request->validate([
            'container_number' => ['required', 'string', 'max:15'],
            'container_type_id' => [
                'nullable', 'integer',
                Rule::exists('container_types', 'id')
                    ->where(fn ($query) => $query->where('active', true)),
            ],
            // Enums existentes en la tabla containers, no inferidos del BL.
            'condition' => ['required', Rule::in(['V', 'D', 'S', 'P', 'L', 'R'])],
            'operational_status' => [
                'required',
                Rule::in([
                    'available', 'allocated', 'loading', 'loaded', 'in_transit',
                    'discharging', 'empty', 'maintenance', 'inspection', 'quarantine',
                ]),
            ],
            // Las columnas reales permiten NULL: desconocido nunca es cero.
            'tare_weight_kg' => [
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99',
            ],
            'max_gross_weight_kg' => [
                'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99',
            ],
            // Opcionales AFIP: únicamente lo que consta en el documento.
            'argentina_container_code' => ['nullable', 'string', 'max:4'],
            'shipper_seal' => ['nullable', 'string', 'max:35'],
            'csc_expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'acep' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
        ], [
            'container_number.required' => 'Ingresá el número del contenedor.',
            'container_number.max' => 'El número admite hasta 15 caracteres.',
            'container_type_id.exists' =>
                'Seleccioná un tipo de contenedor activo del catálogo.',
            'condition.required' =>
                'Seleccioná el estado físico del contenedor.',
            'operational_status.required' =>
                'Seleccioná el estado operativo del contenedor.',
            'tare_weight_kg.decimal' => 'La tara admite hasta dos decimales.',
            'max_gross_weight_kg.decimal' =>
                'El peso bruto máximo admite hasta dos decimales.',
            'argentina_container_code.max' =>
                'El código de características AFIP admite hasta 4 caracteres.',
            'shipper_seal.max' =>
                'El precinto de origen admite hasta 35 caracteres para ATA-DESC.',
            'acep.regex' => 'ACEP solo puede contener letras y números.',
            'acep.max' => 'ACEP admite hasta 20 caracteres.',
            'csc_expiry_date.date_format' =>
                'El vencimiento CSC debe tener una fecha válida (AAAA-MM-DD).',
        ]);

        if (!empty($data['csc_expiry_date']) && !empty($data['acep'])) {
            throw ValidationException::withMessages([
                'acep' =>
                    'Para ATA-DESC se admite la fecha de vencimiento CSC o el código ACEP, pero no ambos simultáneamente.',
            ]);
        }

        if (
            isset($data['tare_weight_kg'], $data['max_gross_weight_kg'])
            && (float) $data['tare_weight_kg']
                > (float) $data['max_gross_weight_kg']
        ) {
            throw ValidationException::withMessages([
                'max_gross_weight_kg' =>
                    'El peso bruto máximo no puede ser menor que la tara declarada.',
            ]);
        }

        try {
            $container = DB::transaction(function () use (
                $operation, $bill, $item, $data
            ) {
                $lockedBill = $operation->billsOfLading()
                    ->whereNull('shipment_id')
                    ->lockForUpdate()
                    ->findOrFail($bill->id);
                $this->assertEditableBill($lockedBill, $operation);

                $lockedItem = $lockedBill->shipmentItems()
                    ->lockForUpdate()
                    ->findOrFail($item->id);
                $this->assertContainerizedDraft($lockedItem);

                // La ficha física es global. Si el número ya existe,
                // exigir el circuito de vinculación sin tocar su contenido.
                if (Container::query()
                    ->where('container_number', $data['container_number'])
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'container_number' =>
                            'Este contenedor ya está registrado en el catálogo. '
                            . 'Para asociarlo a la línea, utilizá «Vincular contenedor existente». '
                            . 'Su ficha no se modificó.',
                    ]);
                }

                $container = Container::create([
                    'container_number' => $data['container_number'],
                    'container_type_id' => $data['container_type_id'] ?? null,
                    'condition' => $data['condition'],
                    'operational_status' => $data['operational_status'],
                    'tare_weight_kg' => $data['tare_weight_kg'] ?? null,
                    'max_gross_weight_kg' => $data['max_gross_weight_kg'] ?? null,
                    'argentina_container_code' =>
                        $data['argentina_container_code'] ?? null,
                    'shipper_seal' => $data['shipper_seal'] ?? null,
                    'csc_expiry_date' => $data['csc_expiry_date'] ?? null,
                    'acep' => $data['acep'] ?? null,
                    // El único H/P declarado corresponde a esta línea.
                    // El XML ATA-DESC usa siempre el H/P contextual del ítem.
                    'container_condition' =>
                        strtoupper(trim((string) $lockedItem->container_condition)),
                    'active' => true,
                    'blocked' => false,
                    'created_by_user_id' => $this->getCurrentUser()?->id,
                ]);

                // Distribución física desconocida: todos los pesos/cantidades
                // del pivote permanecen NULL por diseño del esquema actual.
                $lockedItem->containers()->attach($container->id, [
                    'container_condition' =>
                        strtoupper(trim((string) $lockedItem->container_condition)),
                    'created_by_user_id' => $this->getCurrentUser()?->id,
                ]);

                return $container;
            });
        } catch (QueryException $exception) {
            // Una carrera por el índice único de container_number no debe
            // terminar en un error genérico, ni reutilizar/alterar otra ficha.
            if (
                in_array((string) $exception->getCode(), ['23000', '23505', '19'], true)
                && Container::query()
                    ->where('container_number', $data['container_number'])
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'container_number' =>
                        'El contenedor ya fue registrado en el catálogo. '
                        . 'Podés vincularlo desde «Vincular contenedor existente».',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('company.simple.desconsolidado.operations.show', [
                'operation' => $operation->id,
            ])
            ->with(
                'success',
                "Contenedor {$container->container_number} registrado y vinculado "
                . "a la línea {$item->line_number} como borrador. "
                . 'No se asignaron pesos ni cantidades por contenedor. '
                . 'Antes de transmitir a Aduana, revisá la documentación y las validaciones pendientes.'
            );
    }

    /**
     * El catálogo es global; una vinculación ajena impide editar
     * los documentos CSC/ACEP desde el expediente actual.
     */
    private function containerHasOtherUses(int $containerId, int $operationId): bool
    {
        return DB::table('container_shipment_item as link')
            ->leftJoin('shipment_items as line', 'line.id', '=', 'link.shipment_item_id')
            ->leftJoin('bills_of_lading as title', 'title.id', '=', 'line.bill_of_lading_id')
            ->where('link.container_id', $containerId)
            ->where(function ($query) use ($operationId) {
                $query->whereNull('title.deconsolidation_operation_id')
                    ->orWhere('title.deconsolidation_operation_id', '!=', $operationId);
            })
            ->exists();
    }

    /**
     * Cada ID debe resolverse por su relación con el padre autorizado.
     *
     * @return array{0:\App\Models\Company,1:DeconsolidationOperation,2:BillOfLading,3:ShipmentItem}
     */
    private function authorizedItem(int $operationId, int $billId, int $itemId): array
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

        $bill = $operation->billsOfLading()
            ->whereNull('shipment_id')
            ->findOrFail($billId);

        $this->assertEditableBill($bill, $operation);

        $item = $bill->shipmentItems()->findOrFail($itemId);
        $this->assertContainerizedDraft($item);

        return [$company, $operation, $bill, $item];
    }

    private function assertEditableBill(
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
            'Este conocimiento no está disponible para edición desde el expediente actual.'
        );
    }

    private function assertContainerizedDraft(ShipmentItem $item): void
    {
        abort_unless(
            $item->status === 'draft'
            && trim((string) $item->packaging_code) === '05'
            && in_array(
                strtoupper(trim((string) $item->container_condition)),
                ['H', 'P'],
                true
            ),
            409,
            'Para vincular un contenedor, la línea debe estar en borrador, '
            . 'tener embalaje 05 y contar con una condición H/P válida.'
        );
    }
}
