{{--
    Pantalla de trabajo local de Transbordos Hidrovía.
    El futuro controlador debe suministrar $voyage, $bill (shipmentItems.containers)
    y $shipments (vessel) de la MISMA empresa/viaje, con rol Transbordos.
    Sólo usa las rutas existentes drafts.show/update/preflight: NO transmite SOAP.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Distribución de Transbordos</h1>
                <p class="text-sm text-gray-600">Viaje {{ $voyage->voyage_number }} · Conocimiento {{ $bill->bill_number }}</p>
            </div>
            <a href="{{ route('company.simple.transbordo.index') }}" class="text-sm text-blue-700 hover:underline">Volver a los viajes</a>
        </div>
    </x-slot>

    @php
        $shipmentOptions = $shipments->map(fn ($shipment) => [
            'id' => (int) $shipment->id,
            'label' => $shipment->shipment_number . ' · ' . ($shipment->vessel?->name ?? 'Embarcación sin nombre'),
        ])->values()->all();
        $itemOptions = $bill->shipmentItems->map(fn ($item) => [
            'id' => (int) $item->id,
            'label' => 'Línea ' . $item->line_number . ' · ' . $item->item_description,
            'quantity' => $item->package_quantity,
            'weight' => $item->gross_weight_kg,
            'containers' => $item->containers->map(fn ($container) => [
                'id' => (int) $container->id,
                'number' => $container->container_number,
            ])->values()->all(),
        ])->values()->all();
    @endphp

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5" id="transbordo-editor">
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            <strong>Preparación y pruebas:</strong> esta pantalla permite guardar borradores y validar cómo se distribuye
            un conocimiento entre barcazas. <strong>No envía información a ARCA.</strong>
        </div>

        <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
            <h2 class="font-semibold text-gray-900 mb-3">Mercadería del conocimiento</h2>
            @forelse($bill->shipmentItems as $item)
                <div class="py-2 border-b border-gray-100 last:border-0 text-sm text-gray-700">
                    <strong>Línea {{ $item->line_number }}:</strong> {{ $item->item_description }} ·
                    {{ $item->package_quantity }} bultos · {{ $item->gross_weight_kg }} kg brutos
                    @if($item->containers->isNotEmpty())
                        <span class="block text-xs text-gray-500 mt-1">Contenedores:
                            {{ $item->containers->pluck('container_number')->join(', ') }}
                        </span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-red-700">No hay líneas de mercadería cargadas en este conocimiento.</p>
            @endforelse
        </section>

        <section class="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <div>
                    <h2 class="font-semibold text-gray-900">Distribución por barcaza</h2>
                    <p class="text-sm text-gray-600">Asigná cada línea o contenedor a la barcaza correspondiente, con sus bultos y peso.</p>
                </div>
                <button type="button" id="transbordo-add" class="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200">+ Agregar asignación</button>
            </div>
            <div id="transbordo-rows" class="space-y-3"></div>
            <div class="flex flex-wrap gap-3 mt-5">
                <button id="transbordo-save" type="button" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Guardar borrador</button>
                <button id="transbordo-preflight" type="button" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Validar distribución guardada</button>
            </div>
            <p id="transbordo-message" role="status" aria-live="polite" class="mt-3 text-sm text-gray-700"></p>
        </section>

        <section id="transbordo-result" class="hidden bg-white shadow-sm rounded-lg border border-gray-200 p-4" aria-live="polite">
            <h2 class="font-semibold text-gray-900 mb-2">Resultado del preflight</h2>
            <p class="text-sm text-gray-700 mb-3">La distribución está completa según las validaciones locales. No se transmitió a Aduana.</p>
            <div id="transbordo-result-rows" class="space-y-2"></div>
        </section>
    </div>

    <template id="transbordo-row-template">
        <div class="transbordo-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-md border border-gray-200 bg-gray-50 p-3">
            <label class="md:col-span-3 text-sm font-medium text-gray-700">Barcaza
                <select data-field="shipment_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required></select>
            </label>
            <label class="md:col-span-3 text-sm font-medium text-gray-700">Línea de mercadería
                <select data-field="shipment_item_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required></select>
            </label>
            <label class="md:col-span-2 text-sm font-medium text-gray-700">Contenedor
                <select data-field="container_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></select>
            </label>
            <label class="md:col-span-2 text-sm font-medium text-gray-700">Bultos
                <input data-field="package_quantity" type="number" min="0" step="1" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
            </label>
            <label class="md:col-span-2 text-sm font-medium text-gray-700">Peso kg
                <input data-field="gross_weight_kg" type="number" min="0" step="0.01" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
            </label>
            <button data-action="remove" type="button" class="md:col-span-12 justify-self-end text-sm text-red-700 hover:underline">Quitar</button>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const shipments = {{ \Illuminate\Support\Js::from($shipmentOptions) }};
            const items = {{ \Illuminate\Support\Js::from($itemOptions) }};
            const routes = {
                show: @json(route('company.simple.transbordo.drafts.show', ['voyage' => $voyage->id, 'bill' => $bill->id])),
                update: @json(route('company.simple.transbordo.drafts.update', ['voyage' => $voyage->id, 'bill' => $bill->id])),
                preflight: @json(route('company.simple.transbordo.drafts.preflight', ['voyage' => $voyage->id, 'bill' => $bill->id])),
            };
            const csrf = @json(csrf_token());
            const rows = document.getElementById('transbordo-rows');
            const template = document.getElementById('transbordo-row-template');
            const message = document.getElementById('transbordo-message');
            const result = document.getElementById('transbordo-result');
            const resultRows = document.getElementById('transbordo-result-rows');
            const save = document.getElementById('transbordo-save');
            const preflight = document.getElementById('transbordo-preflight');
            const add = document.getElementById('transbordo-add');
            let dirty = false;
            let busy = false;

            const setMessage = (text, error = false) => {
                message.textContent = text;
                message.className = 'mt-3 text-sm ' + (error ? 'text-red-700' : 'text-gray-700');
            };
            const invalidate = () => { dirty = true; result.classList.add('hidden'); };
            const option = (value, label) => new Option(label, value);
            const fill = (select, entries, placeholder) => {
                select.replaceChildren(option('', placeholder));
                for (const entry of entries) select.add(option(entry.id, entry.label));
            };
            const field = (row, name) => row.querySelector(`[data-field="${name}"]`);
            const refreshContainers = (row, selected = '') => {
                const item = items.find(entry => String(entry.id) === field(row, 'shipment_item_id').value);
                const select = field(row, 'container_id');
                const containers = item?.containers ?? [];
                fill(select, containers.map(c => ({ id: c.id, label: c.number })),
                    containers.length ? 'Seleccionar contenedor' : 'Sin contenedor');
                select.disabled = !containers.length;
                select.required = containers.length > 0;
                select.value = selected == null ? '' : String(selected);
            };
            const addRow = (values = {}) => {
                const row = template.content.firstElementChild.cloneNode(true);
                fill(field(row, 'shipment_id'), shipments, 'Seleccionar barcaza');
                fill(field(row, 'shipment_item_id'), items, 'Seleccionar línea');
                field(row, 'shipment_id').value = values.shipment_id ?? '';
                field(row, 'shipment_item_id').value = values.shipment_item_id ?? '';
                refreshContainers(row, values.container_id);
                field(row, 'package_quantity').value = values.package_quantity ?? '';
                field(row, 'gross_weight_kg').value = values.gross_weight_kg ?? '';
                field(row, 'shipment_item_id').addEventListener('change', () => refreshContainers(row));
                row.addEventListener('input', invalidate);
                row.addEventListener('change', invalidate);
                row.querySelector('[data-action="remove"]').addEventListener('click', () => { row.remove(); invalidate(); });
                rows.appendChild(row);
            };
            const request = async (url, method, data) => {
                const response = await fetch(url, {
                    method,
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf,
                        ...(data ? { 'Content-Type': 'application/json' } : {}) },
                    credentials: 'same-origin',
                    ...(data ? { body: JSON.stringify(data) } : {}),
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const detail = Object.values(json.errors ?? {}).flat().join(' ');
                    throw new Error(detail || json.message || `Error HTTP ${response.status}`);
                }
                return json;
            };
            const lock = (value) => {
                busy = value;
                save.disabled = value;
                preflight.disabled = value;
                add.disabled = value;
            };
            add.addEventListener('click', () => { addRow(); invalidate(); });
            save.addEventListener('click', async () => {
                if (busy) return;
                const allocations = [...rows.querySelectorAll('.transbordo-row')].map(row => ({
                    shipment_id: field(row, 'shipment_id').value,
                    shipment_item_id: field(row, 'shipment_item_id').value,
                    container_id: field(row, 'container_id').value || null,
                    package_quantity: field(row, 'package_quantity').value,
                    gross_weight_kg: field(row, 'gross_weight_kg').value,
                }));
                if (!allocations.length) return setMessage('Agregá al menos una asignación.', true);
                if ([...rows.querySelectorAll('input,select:not(:disabled)')].some(input => !input.checkValidity())) {
                    return setMessage('Completá barcaza, línea, contenedor (si aplica), bultos y peso.', true);
                }
                lock(true);
                try {
                    await request(routes.update, 'PUT', { allocations });
                    dirty = false;
                    setMessage('Borrador guardado. Ahora podés validar la distribución.');
                } catch (error) { setMessage(error.message, true); }
                finally { lock(false); }
            });
            preflight.addEventListener('click', async () => {
                if (busy) return;
                if (dirty) return setMessage('Guardá los cambios antes de validar.', true);
                lock(true);
                try {
                    const data = await request(routes.preflight, 'POST');
                    resultRows.replaceChildren();
                    for (const shipment of data.distribution?.shipments ?? []) {
                        const p = document.createElement('p');
                        p.className = 'text-sm border-b border-gray-100 py-2 text-gray-800';
                        p.textContent = `Envío ${shipment.id_envio} · ${shipment.shipment_number} · ${shipment.lines.length} asignaciones`;
                        resultRows.appendChild(p);
                    }
                    result.classList.remove('hidden');
                    setMessage('Distribución válida en la aplicación. Sin envío a ARCA.');
                } catch (error) { result.classList.add('hidden'); setMessage(error.message, true); }
                finally { lock(false); }
            });
            lock(true);
            request(routes.show, 'GET').then(data => {
                (data.allocations?.length ? data.allocations : [{}]).forEach(addRow);
                dirty = false;
                setMessage('Distribución cargada. Revisá las asignaciones antes de guardarlas.');
            }).catch(error => setMessage(error.message, true)).finally(() => lock(false));
        });
    </script>
</x-app-layout>
