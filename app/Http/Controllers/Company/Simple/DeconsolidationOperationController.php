<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DeconsolidationOperation;
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
                    ? 'Desconsolidación iniciada. Podés continuar con los conocimientos hijos.'
                    : 'Ya existe esta desconsolidación. Retomamos el expediente guardado.'
            );
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

    private function authorizedCompany(): Company
    {
        $company = $this->getUserCompany();

        abort_unless($company, 403, 'No se encontró una empresa asociada al usuario.');
        abort_unless(
            $this->hasCompanyRole('Desconsolidador'),
            403,
            'La empresa no tiene habilitado el rol Desconsolidador.'
        );

        return $company;
    }
}
