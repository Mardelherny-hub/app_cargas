<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Simple\ArgentinaDeconsolidatedService;
use App\Traits\UserHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Entrada HTTP canónica del circuito ATA Desconsolidador Argentina.
 */
class ArgentinaDeconsolidatedController extends Controller
{
    use UserHelper;

    public function show(Voyage $voyage): View
    {
        $company = $this->authorizeVoyage($voyage);

        $voyage->load([
            'leadVessel',
            'originPort',
            'destinationPort',
            'company',
            'billsOfLading.loadingPort',
            'billsOfLading.dischargePort',
            'billsOfLading.shipmentItems.containers',
        ]);

        $desconsolidatedBills = $voyage->billsOfLading
            ->whereNotNull('master_bill_number')
            ->sortBy('id')
            ->values();

        $containersCount = $desconsolidatedBills
            ->flatMap(fn ($bill) => $bill->shipmentItems)
            ->flatMap(fn ($item) => $item->containers)
            ->unique('id')
            ->count();

        $service = new ArgentinaDeconsolidatedService(
            $company,
            $this->getCurrentUser()
        );
        $validation = $service->canProcessVoyage($voyage);

        $billStates = $desconsolidatedBills->isEmpty()
            ? []
            : $service->billLifecycleStates(
                $voyage,
                $desconsolidatedBills->pluck('id')->all()
            );

        $transactions = WebserviceTransaction::query()
            ->where('company_id', $company->id)
            ->where('voyage_id', $voyage->id)
            ->where('webservice_type', 'desconsolidado')
            ->where('country', 'AR')
            ->with('user')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('company.simple.desconsolidado.show', [
            'voyage' => $voyage,
            'company' => $company,
            'desconsolidatedBills' => $desconsolidatedBills,
            'desconsolidatedBillsCount' => $desconsolidatedBills->count(),
            'containersCount' => $containersCount,
            'validation' => $validation,
            'billStates' => $billStates,
            'estados' => $this->aggregateStates($billStates),
            'transactions' => $transactions,
        ]);
    }

    public function send(Request $request, Voyage $voyage): RedirectResponse
    {
        $company = $this->authorizeVoyage($voyage);

        $validated = $request->validate([
            'action' => 'required|in:registrar,rectificar,eliminar',
            'bill_ids' => 'required|array|min:1',
            'bill_ids.*' => 'integer|min:1',
        ], [
            'bill_ids.required' => 'Seleccione al menos un conocimiento.',
            'bill_ids.min' => 'Seleccione al menos un conocimiento.',
        ]);

        $action = $validated['action'];
        $billIds = $validated['bill_ids'];
        $service = new ArgentinaDeconsolidatedService(
            $company,
            $this->getCurrentUser()
        );

        try {
            $result = match ($action) {
                'registrar' => $service->registrarTitulos($voyage, $billIds),
                'rectificar' => $service->rectificarTitulos($voyage, $billIds),
                'eliminar' => $service->eliminarTitulos($voyage, $billIds),
            };

            if ($result['success']) {
                $processedCount = count($result['bill_ids'] ?? $billIds);
                $messages = [
                    'registrar' => "{$processedCount} título(s) desconsolidado(s) registrado(s) exitosamente en AFIP.",
                    'rectificar' => "{$processedCount} título(s) desconsolidado(s) rectificado(s) exitosamente en AFIP.",
                    'eliminar' => "{$processedCount} título(s) desconsolidado(s) eliminado(s) exitosamente en AFIP.",
                ];

                $redirect = redirect()
                    ->route('company.simple.desconsolidado.show', $voyage)
                    ->with('success', $messages[$action]);

                if (!empty($result['warnings'])) {
                    $redirect->with(
                        'warning',
                        $this->formatWarnings($result['warnings'])
                    );
                }

                return $redirect;
            }

            return redirect()
                ->route('company.simple.desconsolidado.show', $voyage)
                ->withInput()
                ->with(
                    'error',
                    'Error: ' . ($result['error_message'] ?? $result['error'] ?? 'Error desconocido')
                );
        } catch (Throwable $exception) {
            Log::error('Error HTTP procesando desconsolidado Argentina', [
                'voyage_id' => $voyage->id,
                'company_id' => $company->id,
                'action' => $action,
                'bill_ids' => $billIds,
                'user_id' => $this->getCurrentUser()?->id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('company.simple.desconsolidado.show', $voyage)
                ->withInput()
                ->with('error', 'Error procesando solicitud: ' . $exception->getMessage());
        }
    }

    private function authorizeVoyage(Voyage $voyage)
    {
        $company = $this->getUserCompany();

        abort_unless(
            $company,
            403,
            'No se encontró la empresa asociada al usuario.'
        );
        abort_unless(
            $this->hasCompanyRole('Desconsolidador'),
            403,
            'La empresa no tiene habilitado el rol Desconsolidador.'
        );
        abort_unless(
            (int) $voyage->company_id === (int) $company->id,
            403,
            'No tiene permisos para este viaje.'
        );

        return $company;
    }

    /**
     * Resumen visual del viaje. El estado jurídico-operativo sigue siendo
     * billStates; este resumen sólo conserva compatibilidad con la vista.
     */
    private function aggregateStates(array $billStates): array
    {
        $states = [
            'registrar' => 'pending',
            'rectificar' => 'pending',
            'eliminar' => 'pending',
        ];

        if ($billStates === []) {
            return $states;
        }

        $values = array_values($billStates);
        $active = array_filter(
            $values,
            fn ($state) => in_array($state, ['registrar', 'rectificar'], true)
        );
        $deleted = array_filter($values, fn ($state) => $state === 'eliminar');
        $rectified = array_filter($values, fn ($state) => $state === 'rectificar');

        if ($active !== []) {
            $states['registrar'] = 'success';
        }
        if ($rectified !== []) {
            $states['rectificar'] = 'success';
        }
        if ($deleted !== []) {
            $states['eliminar'] = 'success';
        }

        return $states;
    }

    private function formatWarnings(array $warnings): string
    {
        return collect($warnings)
            ->map(function ($warning) {
                if (!is_array($warning)) {
                    return (string) $warning;
                }

                return implode(' - ', array_filter([
                    $warning['code'] ?? null,
                    $warning['description'] ?? null,
                    $warning['additional'] ?? null,
                ]));
            })
            ->filter()
            ->implode(' | ');
    }
}
