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
 *
 * Mantiene la vista y las URLs históricas, pero usa únicamente el
 * webservice_type real de la base: "desconsolidado".
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
            ->values();

        $containersCount = $desconsolidatedBills
            ->flatMap(fn ($bill) => $bill->shipmentItems)
            ->flatMap(fn ($item) => $item->containers)
            ->unique('id')
            ->count();

        $service = new ArgentinaDeconsolidatedService($company, $this->getCurrentUser());
        $validation = $service->canProcessVoyage($voyage);

        $transactions = WebserviceTransaction::query()
            ->where('company_id', $company->id)
            ->where('voyage_id', $voyage->id)
            ->where('webservice_type', 'desconsolidado')
            ->where('country', 'AR')
            ->with('user')
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('company.simple.desconsolidado.show', [
            'voyage' => $voyage,
            'company' => $company,
            'desconsolidatedBills' => $desconsolidatedBills,
            'desconsolidatedBillsCount' => $desconsolidatedBills->count(),
            'containersCount' => $containersCount,
            'validation' => $validation,
            'estados' => $this->operationStates($transactions),
            'transactions' => $transactions,
        ]);
    }

    public function send(Request $request, Voyage $voyage): RedirectResponse
    {
        $company = $this->authorizeVoyage($voyage);

        $validated = $request->validate([
            'action' => 'required|in:registrar,rectificar,eliminar',
            'bill_ids' => 'nullable|array',
            'bill_ids.*' => 'integer|min:1',
        ]);

        $action = $validated['action'];
        $billIds = $validated['bill_ids'] ?? [];
        $service = new ArgentinaDeconsolidatedService($company, $this->getCurrentUser());

        try {
            $result = match ($action) {
                'registrar' => $service->registrarTitulos($voyage, $billIds),
                'rectificar' => $service->rectificarTitulos($voyage, $billIds),
                'eliminar' => $service->eliminarTitulos($voyage, $billIds),
            };

            if ($result['success']) {
                $messages = [
                    'registrar' => 'Títulos desconsolidados registrados exitosamente en AFIP.',
                    'rectificar' => 'Títulos desconsolidados rectificados exitosamente en AFIP.',
                    'eliminar' => 'Títulos desconsolidados eliminados exitosamente en AFIP.',
                ];

                $redirect = redirect()
                    ->route('company.simple.desconsolidado.show', $voyage)
                    ->with('success', $messages[$action]);

                if (!empty($result['warnings'])) {
                    $redirect->with('warning', $this->formatWarnings($result['warnings']));
                }

                return $redirect;
            }

            return redirect()
                ->route('company.simple.desconsolidado.show', $voyage)
                ->with('error', 'Error: ' . ($result['error_message'] ?? $result['error'] ?? 'Error desconocido'));
        } catch (Throwable $e) {
            Log::error('Error HTTP procesando desconsolidado Argentina', [
                'voyage_id' => $voyage->id,
                'company_id' => $company->id,
                'action' => $action,
                'user_id' => $this->getCurrentUser()?->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('company.simple.desconsolidado.show', $voyage)
                ->with('error', 'Error procesando solicitud: ' . $e->getMessage());
        }
    }

    private function authorizeVoyage(Voyage $voyage)
    {
        $company = $this->getUserCompany();

        abort_unless($company, 403, 'No se encontró la empresa asociada al usuario.');
        abort_unless($this->hasCompanyRole('Desconsolidador'), 403, 'La empresa no tiene habilitado el rol Desconsolidador.');
        abort_unless((int) $voyage->company_id === (int) $company->id, 403, 'No tiene permisos para este viaje.');

        return $company;
    }

    private function operationStates($transactions): array
    {
        $states = [
            'registrar' => 'pending',
            'rectificar' => 'pending',
            'eliminar' => 'pending',
        ];

        $latestSuccess = $transactions->first(fn ($transaction) => $transaction->status === 'success');
        $method = $latestSuccess?->additional_metadata['method'] ?? null;

        if ($method === 'registrar') {
            $states['registrar'] = 'success';
        } elseif ($method === 'rectificar') {
            $states['registrar'] = 'success';
            $states['rectificar'] = 'success';
        } elseif ($method === 'eliminar') {
            // Ciclo cerrado: queda evidencia de eliminación y se habilita un nuevo registro.
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
