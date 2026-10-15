<?php

namespace App\Http\Controllers\Company\Simple;

use App\Http\Controllers\Controller;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\DeconsolidationOperation;
use App\Models\ShipmentItem;
use App\Traits\UserHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Vinculación ATA-DESC de contenedores existentes con líneas de mercadería.
 *
 * El catálogo de contenedores es global. Este flujo nunca crea ni modifica
 * un contenedor físico, y no inventa pesos/cantidades para el pivote.
 * No realiza ninguna operación ante Aduana.
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
                'Ingresá el número real del contenedor que corresponde a esta línea.',
            'container_number.max' =>
                'El número del contenedor admite hasta 15 caracteres en el sistema.',
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
                        'Ese contenedor no figura como disponible en el catálogo. '
                        . 'No se creó uno automáticamente: verificá su alta con datos reales.',
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
                            . 'del mismo conocimiento con condición H/P distinta.',
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
                    ? "Contenedor {$container->container_number} vinculado como borrador "
                        . "a la línea {$item->line_number}. "
                        . 'No se inventaron pesos ni cantidades por contenedor. '
                        . 'Falta verificar su documentación antes de transmitir.'
                    : "El contenedor {$container->container_number} ya estaba "
                        . "vinculado a la línea {$item->line_number}; "
                        . 'no se duplicó ni se modificó su ficha.'
            );
    }

    /**
     * Cada ID debe resolverse por su relación con el padre autorizado.
     *
     * @return array{0:\App\Models\Company,1:DeconsolidationOperation,2:BillOfLading,3:ShipmentItem}
     */
    private function authorizedItem(int $operationId, int $billId, int $itemId): array
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
            'El conocimiento no es un borrador editable de este expediente.'
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
            'Solo pueden vincularse contenedores a líneas contenedorizadas '
            . '(embalaje 05) que sigan siendo borradores y tengan condición H/P.'
        );
    }
}
