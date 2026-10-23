<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DeconsolidationOperation;
use App\Models\WebserviceTransaction;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use App\Services\Simple\WebserviceEnvironment;
use App\Services\Simple\ArgentinaDeconsolidatedService;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Traits\UserHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ingreso independiente ATA-DESC. No crea viajes ni embarcaciones.
 *
 * Las rutas y vistas de esta pantalla se incorporan por separado.
 */
class DeconsolidationOperationController extends Controller
{
    use UserHelper;

    public function index(): View
    {
        $company = $this->authorizedCompany();

        $operations = DeconsolidationOperation::query()
            ->forCompany((int) $company->id)
            ->withCount('billsOfLading')
            ->orderByDesc('id')
            ->paginate(15);

        return view('company.simple.desconsolidado.operations.index', compact(
            'company',
            'operations'
        ));
    }

    public function create(): View
    {
        $company = $this->authorizedCompany();

        return view('company.simple.desconsolidado.operations.create', compact('company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->authorizedCompany();

        // Limpiar espacios sin alterar el número del conocimiento madre.
        foreach ([
            'argentina_voyage_id',
            'master_loading_port_code',
            'master_bill_number',
        ] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }

        if (is_string($request->input('master_loading_port_code'))) {
            $request->merge([
                'master_loading_port_code' => strtoupper(
                    $request->input('master_loading_port_code')
                ),
            ]);
        }

        $data = $request->validate([
            'argentina_voyage_id' => ['required', 'string', 'max:16'],
            'master_loading_port_code' => ['required', 'string', 'regex:/^[A-Z0-9]{5}$/'],
            'master_bill_number' => ['required', 'string', 'max:18'],
        ], [
            'argentina_voyage_id.required' =>
                'Ingresá el identificador del viaje que informó Aduana.',
            'argentina_voyage_id.max' =>
                'El identificador del viaje admite hasta 16 caracteres.',
            'master_loading_port_code.required' =>
                'Ingresá el código del puerto de embarque del conocimiento madre.',
            'master_loading_port_code.regex' =>
                'El código del puerto debe tener 5 letras o números.',
            'master_bill_number.required' =>
                'Ingresá el número del conocimiento madre.',
            'master_bill_number.max' =>
                'El conocimiento madre admite hasta 18 caracteres.',
        ]);

        // La empresa siempre proviene de la sesión autenticada, nunca del formulario.
        // El índice único de la migración evita duplicados incluso en concurrencia.
        $operation = DeconsolidationOperation::query()->firstOrCreate([
            'company_id' => (int) $company->id,
            'argentina_voyage_id' => $data['argentina_voyage_id'],
            'master_loading_port_code' => $data['master_loading_port_code'],
            'master_bill_number' => $data['master_bill_number'],
        ], [
            'created_by_user_id' => $this->getCurrentUser()?->id,
        ]);

        return redirect()
            ->route('company.simple.desconsolidado.operations.show', [
                'operation' => $operation->id,
            ])
            ->with(
                'success',
                $operation->wasRecentlyCreated
                    ? 'Expediente de desconsolidación creado. Podés continuar con los conocimientos hijos.'
                    : 'El expediente ya estaba registrado. Se abrió la operación existente para continuar.'
            );
    }

    /**
     * Corrige las referencias del expediente exclusivamente mientras no tenga
     * conocimientos hijos. La edición nunca crea ni modifica viajes locales.
     */
    public function update(Request $request, int $operation): RedirectResponse
    {
        $company = $this->authorizedCompany();

        foreach ([
            'argentina_voyage_id',
            'master_loading_port_code',
            'master_bill_number',
        ] as $field) {
            if (is_string($request->input($field))) {
                $value = trim($request->input($field));
                $request->merge([
                    $field => $field === 'master_loading_port_code'
                        ? strtoupper($value)
                        : $value,
                ]);
            }
        }

        $data = $request->validate([
            'argentina_voyage_id' => ['required', 'string', 'max:16'],
            'master_loading_port_code' => [
                'required', 'string', 'regex:/^[A-Z0-9]{5}$/',
            ],
            'master_bill_number' => ['required', 'string', 'max:18'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use (
            $company, $operation, $data
        ) {
            $record = DeconsolidationOperation::query()
                ->forCompany((int) $company->id)
                ->lockForUpdate()
                ->findOrFail($operation);

            abort_if(
                $record->billsOfLading()->exists(),
                409,
                'Las referencias del expediente no pueden modificarse mientras tenga conocimientos hijos. '
                . 'Revisá la documentación asociada antes de solicitar cambios.'
            );

            $record->update($data);
        });

        return redirect()
            ->route('company.simple.desconsolidado.operations.show', [
                'operation' => $operation,
            ])
            ->with('success', 'Referencias del expediente actualizadas.');
    }

    /** Actualiza únicamente la fecha documentada; no modifica títulos ni contenedores. */
    public function updateArrivalDate(Request $request, int $operation): RedirectResponse
    {
        $company = $this->authorizedCompany();
        $data = $request->validate([
            'arrival_date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'arrival_date.date_format' => 'Ingresá una fecha de arribo válida.',
        ]);

        $record = DeconsolidationOperation::query()
            ->forCompany((int) $company->id)
            ->findOrFail($operation);

        $record->update(['arrival_date' => $data['arrival_date'] ?? null]);

        return redirect()
            ->route('company.simple.desconsolidado.operations.show', [
                'operation' => $record->id,
            ])
            ->with('success', $record->arrival_date
                ? 'Fecha de arribo guardada. Podés volver a validar el expediente.'
                : 'Fecha de arribo eliminada del expediente.');
    }

    public function show(int $operation): View
    {
        $company = $this->authorizedCompany();

        // Nunca resolver un ID global de expediente sin filtrar por empresa.
        $operation = DeconsolidationOperation::query()
            ->forCompany((int) $company->id)
            ->withCount('billsOfLading')
            ->with(['billsOfLading' => fn ($query) => $query->orderBy('id')])
            ->findOrFail($operation);

        return view('company.simple.desconsolidado.operations.show', compact(
            'company',
            'operation'
        ));
    }

    /** Solo diagnóstico local; nunca obtiene WSAA ni invoca SOAP. */
    public function preflight(int $operation): RedirectResponse
    {
        $company = $this->authorizedCompany();
        $record = DeconsolidationOperation::query()
            ->forCompany((int) $company->id)->findOrFail($operation);
        $billIds = $record->billsOfLading()->whereNull('shipment_id')
            ->pluck('bills_of_lading.id')->map(fn ($id) => (int) $id)->all();

        $generator = new SimpleXmlGeneratorDesconsolidado($record, [
            'environment' => WebserviceEnvironment::resolve($company),
        ]);
        $result = $generator->validateLocalData('registrar', $billIds);
        $errors = $result['errors'] ?? [];
        if (!$billIds) {
            $errors[] = 'Agregá al menos un conocimiento hijo para validar el expediente.';
        }
        $blocked = $this->unresolvedTransmission($company, $record, $billIds);
        if ($blocked) {
            $errors[] = $this->unresolvedTransmissionMessage($blocked);
        }
        if ($errors) {
            return redirect()->route('company.simple.desconsolidado.operations.show', [
                'operation' => $record->id,
            ])->withErrors($errors);
        }
        return redirect()->route('company.simple.desconsolidado.operations.show', [
            'operation' => $record->id,
        ])->with('success', 'La revisión local de datos terminó sin observaciones. No se transmitió información a Aduana.');
    }

    public function transmit(Request $request, int $operation): RedirectResponse
    {
        $company = $this->authorizedCompany();
        $record = DeconsolidationOperation::query()
            ->forCompany((int) $company->id)->findOrFail($operation);
        $data = $request->validate([
            'method' => ['required', 'in:registrar,rectificar,eliminar'],
            'bill_ids' => ['required', 'array', 'min:1'],
            'bill_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'confirmation' => ['required', 'accepted'],
        ]);

        // Diferenciar faltantes locales de un resultado SOAP incierto.
        // El mismo validador se ejecuta en el servicio antes de pedir WSAA;
        // aquí devolvemos sus errores al formulario sin crear transacciones.
        $validation = (new SimpleXmlGeneratorDesconsolidado($record, [
            'environment' => WebserviceEnvironment::resolve($company),
        ]))->validateLocalData($data['method'], $data['bill_ids']);
        if (!($validation['is_valid'] ?? false)) {
            return redirect()->route('company.simple.desconsolidado.operations.show', [
                'operation' => $record->id,
            ])->withErrors($validation['errors'] ?? ['Revisá los datos de los conocimientos seleccionados.']);
        }

        $blocked = $this->unresolvedTransmission($company, $record, $data['bill_ids']);
        if ($blocked) {
            return redirect()->route('company.simple.desconsolidado.operations.show', [
                'operation' => $record->id,
            ])->withErrors([$this->unresolvedTransmissionMessage($blocked)]);
        }

        try {
            $result = (new ArgentinaDeconsolidatedService(
                $company, $this->getCurrentUser()
            ))->ejecutarExpediente($record, $data['method'], $data['bill_ids']);
            return redirect()->route('company.simple.desconsolidado.operations.show', [
                'operation' => $record->id,
            ])->with($result['success'] ? 'success' : 'error',
                $result['success']
                    ? 'Aduana aceptó la operación. Referencia de transacción: ' . $result['transaction_id']
                    : (trim((string) ($result['error_message'] ?? '')) !== ''
                        ? $result['error_message']
                        : 'No se confirmó la aceptación de la operación. Revisá el historial de transmisiones y el resultado informado por Aduana.'));
        } catch (Throwable $exception) {
            Log::error('ATA-DESC: resultado de transmisión no confirmado', [
                'operation_id' => $record->id,
                'method' => $data['method'],
                'error' => $exception->getMessage(),
            ]);
            return redirect()->route('company.simple.desconsolidado.operations.show', [
                'operation' => $record->id,
            ])->with('error', 'No fue posible confirmar el resultado de la operación. Antes de realizar un nuevo envío, revisá el historial y verificá el estado en Aduana.');
        }
    }

    /**
     * Solo consulta el historial del expediente y los títulos seleccionados.
     * El servicio vuelve a verificar el bloqueo bajo lock antes de transmitir.
     */
    private function unresolvedTransmission(
        Company $company,
        DeconsolidationOperation $operation,
        array $billIds
    ): ?WebserviceTransaction {
        $selected = array_map('intval', $billIds);

        return WebserviceTransaction::query()
            ->where('company_id', $company->id)
            ->where('webservice_type', 'desconsolidado')
            ->where('country', 'AR')
            ->whereNull('voyage_id')
            ->where(function ($query) {
                $query->whereIn('status', ['pending', 'sending'])
                    ->orWhere(function ($rejected) {
                        $rejected->where('status', 'error')
                            ->where('requires_manual_review', true)
                            ->whereNull('reviewed_at');
                    });
            })
            ->orderByDesc('id')
            ->get()
            ->first(function (WebserviceTransaction $entry) use ($operation, $selected): bool {
                $metadata = $entry->additional_metadata ?? [];
                if ((int) ($metadata['deconsolidation_operation_id'] ?? 0) !== (int) $operation->id) {
                    return false;
                }
                $historyIds = $metadata['bill_ids'] ?? [];
                return is_array($historyIds)
                    && (bool) array_intersect($selected, array_map('intval', $historyIds));
            });
    }

    private function unresolvedTransmissionMessage(WebserviceTransaction $transaction): string
    {
        if ($transaction->status === 'error') {
            $code = trim((string) ($transaction->error_code ?? ''));
            return 'La transacción ' . $transaction->id
                . ' tiene un rechazo pendiente de revisión'
                . ($code !== '' ? ' (código ARCA ' . $code . ')' : '')
                . '. Verificá y conciliá su estado con Aduana antes de reenviar.';
        }

        return 'La transacción ' . $transaction->id
            . ' tiene un resultado pendiente o no confirmado. '
            . 'Verificá su estado con Aduana antes de reenviar.';
    }

    private function authorizedCompany(): Company
    {
        $company = $this->getUserCompany();

        abort_unless($company, 403, 'Tu usuario no tiene una empresa asociada. Consultá al administrador.');
        abort_unless(
            $this->hasCompanyRole('Desconsolidador'),
            403,
            'La empresa no está habilitada para operar Desconsolidados. Consultá al administrador.'
        );

        return $company;
    }
}
