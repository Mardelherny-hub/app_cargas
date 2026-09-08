<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\Container;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Simple\ArgentinaDeconsolidatedService;
use App\Traits\UserHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $customsContainers = $this->customsContainers($desconsolidatedBills);
        $containersCount = $customsContainers->count();

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
            'customsContainers' => $customsContainers,
            'validation' => $validation,
            'billStates' => $billStates,
            'estados' => $this->aggregateStates($billStates),
            'transactions' => $transactions,
        ]);
    }

    public function saveContainerCustoms(
        Request $request,
        Voyage $voyage,
        Container $container
    ): RedirectResponse {
        $this->authorizeVoyage($voyage);
        $this->authorizeContainerForVoyage($voyage, $container);

        $validated = $request->validate([
            'csc_expiry_date' => 'nullable|date',
            'acep' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'item_conditions' => 'required|array|min:1',
            'item_conditions.*' => 'required|in:H,P',
        ], [
            'acep.regex' => 'ACEP sólo puede contener letras y números.',
            'acep.max' => 'ACEP no puede superar 20 caracteres.',
            'item_conditions.required' => 'Debe informar H/P para las líneas vinculadas al contenedor.',
            'item_conditions.*.required' => 'Debe informar H/P para cada línea vinculada al contenedor.',
            'item_conditions.*.in' => 'La condición aduanera debe ser H o P.',
        ]);

        $expiry = $validated['csc_expiry_date'] ?? null;
        $acep = isset($validated['acep'])
            ? trim((string) $validated['acep'])
            : null;
        $acep = $acep === '' ? null : $acep;

        if (!$expiry && !$acep) {
            return redirect()
                ->route('company.simple.desconsolidado.show', $voyage)
                ->withErrors([
                    'container_customs' =>
                        'El contenedor debe tener Fecha de vencimiento CSC o ACEP para ATA-DESC.',
                ]);
        }

        $submittedItemIds = collect(array_keys($validated['item_conditions']))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $allowedItems = ShipmentItem::query()
            ->whereIn('id', $submittedItemIds->all())
            ->whereHas('containers', fn ($query) => $query->where('containers.id', $container->id))
            ->whereHas('billOfLading', function ($query) use ($voyage) {
                $query->where('shipment_id', '!=', null)
                    ->whereNotNull('master_bill_number')
                    ->whereHas('shipment', fn ($shipmentQuery) => $shipmentQuery->where('voyage_id', $voyage->id));
            })
            ->get()
            ->keyBy('id');

        if ($allowedItems->count() !== $submittedItemIds->count()) {
            abort(404, 'Una o más líneas no pertenecen a este contenedor/viaje desconsolidado.');
        }

        DB::transaction(function () use ($container, $expiry, $acep, $validated, $allowedItems) {
            // Sólo datos documentales del contenedor específicos de ATA-DESC.
            $container->update([
                'csc_expiry_date' => $expiry,
                'acep' => $acep,
                'last_updated_date' => now(),
                'last_updated_by_user_id' => $this->getCurrentUser()?->id,
            ]);

            // H/P pertenece al contexto de cada línea/BL y no reemplaza el estado
            // físico/operativo L/V/D/S/R del contenedor.
            foreach ($allowedItems as $item) {
                $condition = $validated['item_conditions'][(string) $item->id]
                    ?? $validated['item_conditions'][$item->id]
                    ?? null;

                $item->update([
                    'container_condition' => $condition,
                    'last_updated_date' => now(),
                    'last_updated_by_user_id' => $this->getCurrentUser()?->id,
                ]);
            }
        });

        return redirect()
            ->route('company.simple.desconsolidado.show', $voyage)
            ->with(
                'success',
                "Datos ATA-DESC del contenedor {$container->container_number} actualizados."
            );
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

    private function authorizeContainerForVoyage(
        Voyage $voyage,
        Container $container
    ): void {
        $belongs = $voyage->billsOfLading()
            ->whereNotNull('master_bill_number')
            ->whereHas('shipmentItems.containers', function ($query) use ($container) {
                $query->where('containers.id', $container->id);
            })
            ->exists();

        abort_unless(
            $belongs,
            404,
            'El contenedor no pertenece a un título desconsolidado de este viaje.'
        );
    }

    private function customsContainers($desconsolidatedBills)
    {
        $rows = collect();

        foreach ($desconsolidatedBills as $bill) {
            foreach ($bill->shipmentItems as $item) {
                foreach ($item->containers as $container) {
                    $existing = $rows->get($container->id, [
                        'container' => $container,
                        'items' => [],
                    ]);

                    $existing['items'][$item->id] = [
                        'id' => $item->id,
                        'bill_number' => (string) $bill->bill_number,
                        'line_number' => (string) $item->line_number,
                        'condition' => in_array($item->container_condition, ['H', 'P'], true)
                            ? $item->container_condition
                            : null,
                    ];

                    $existing['items'] = array_values($existing['items']);
                    $rows->put($container->id, $existing);
                }
            }
        }

        return $rows->values();
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
