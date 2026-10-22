{{--
    Distribución de Transbordos Hidrovía. El controlador autoriza empresa y rol.
    Sólo el administrador de una empresa configurada en homologación puede
    preparar/confirmar un envío; nunca se exponen Token, Sign ni el SOAP.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Distribución de Transbordos</h1>
                <p class="text-sm text-gray-600">Viaje {{ $voyage->voyage_number }} · Conocimiento {{ $bill->bill_number }}</p>
            </div>
            <a href="{{ route('company.simple.micdta.show', $voyage) }}" class="text-sm text-blue-700 hover:underline">Volver al MIC/DTA del viaje</a>
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
        $canHomologate = auth()->user()?->hasRole('company-admin')
            && $voyage->company?->ws_environment === 'testing'
            && !$bill->is_fractional
            && $bill->shipmentItems->every(fn ($item) => $item->containers->isEmpty());
    @endphp

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5" id="transbordo-editor">
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            <strong>Preparación:</strong> guardar y validar la distribución <strong>no transmite a ARCA</strong>.
            @if($canHomologate)
                La transmisión es un paso separado, exclusivamente contra homologación, con reserva y confirmación expresa.
            @else
                La transmisión no está disponible para esta operación o usuario.
            @endif
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

        @if($canHomologate)
            <section id="transbordo-homologation" class="hidden bg-white shadow-sm rounded-lg border border-amber-300 p-4" aria-live="polite">
                <h2 class="font-semibold text-gray-900 mb-2">Homologación ARCA · RegistrarTitEnvios</h2>
                <p class="text-sm text-gray-700 mb-4">Solo para carga suelta no fraccionada. El envío inicial registra el conocimiento y un envío por barcaza.
                    Se requiere que el embarque de origen tenga un único conocimiento.
                    Completá los datos aduaneros reales de cada barcaza; no se generan automáticamente.</p>
                <div id="transbordo-customs-rows" class="space-y-4"></div>
                <button id="transbordo-reserve" type="button" disabled
                    class="mt-4 rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                    Preparar reserva (sin enviar)
                </button>
                <div id="transbordo-reserved" class="hidden mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900">Reserva creada: <span id="transbordo-reservation-id"></span></p>
                    <p class="mt-2 text-sm text-amber-900">Revisá la distribución y los datos ingresados. El paso siguiente
                        transmite realmente al servidor de <strong>homologación</strong> de ARCA.
                        Si el resultado es incierto, no repitas la operación.</p>
                    <label class="mt-3 flex gap-2 text-sm text-amber-900">
                        <input id="transbordo-confirm" type="checkbox" class="rounded border-amber-600">
                        Confirmo que revisé este conocimiento y autorizo su envío a homologación.
                    </label>
                    <button id="transbordo-send" type="button" disabled
                        class="mt-3 rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                        Confirmar y enviar a homologación
                    </button>
                </div>
                <div id="transbordo-homologation-result" role="status" aria-live="polite" class="mt-3 text-sm text-gray-700"></div>
            </section>
        @endif
    </div>

    @if($canHomologate)
        <template id="transbordo-customs-template">
            <fieldset class="transbordo-customs-row rounded-md border border-gray-200 bg-gray-50 p-3">
                <legend class="text-sm font-semibold text-gray-800" data-barge-name></legend>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <label class="text-xs text-gray-700">CUIT ATA MIC de esta barcaza
                        <input data-customs="idFiscalATAMIC" required pattern="[0-9]{11,14}" maxlength="14"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm" autocomplete="off">
                    </label>
                    <label class="text-xs text-gray-700">Origen · Código lugar operativo
                        <input data-customs="lugOperOrigen.codLugOper" required maxlength="5"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm" autocomplete="off">
                    </label>
                    <label class="text-xs text-gray-700">Origen · Código ciudad (UN/LOCODE)
                        <input data-customs="lugOperOrigen.codCiu" required pattern="[A-Za-z]{2}[A-Za-z0-9]{3}" maxlength="5"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm" autocomplete="off">
                    </label>
                    <label class="text-xs text-gray-700">Destino · Código lugar operativo
                        <input data-customs="lugOperDestino.codLugOper" required maxlength="7"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm" autocomplete="off">
                    </label>
                    <label class="text-xs text-gray-700">Destino · Código ciudad (UN/LOCODE)
                        <input data-customs="lugOperDestino.codCiu" required pattern="[A-Za-z]{2}[A-Za-z0-9]{3}" maxlength="5"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm" autocomplete="off">
                    </label>
                </div>
            </fieldset>
        </template>
    @endif

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
                @if($canHomologate)
                reserve: @json(route('company.simple.transbordo.homologation.reserve', ['voyage' => $voyage->id, 'bill' => $bill->id])),
                send: @json(route('company.simple.transbordo.homologation.send', ['voyage' => $voyage->id, 'bill' => $bill->id])),
                @endif
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
            let frozen = false;
            @if($canHomologate)
            const homologation = document.getElementById('transbordo-homologation');
            const customsRows = document.getElementById('transbordo-customs-rows');
            const customsTemplate = document.getElementById('transbordo-customs-template');
            const reserve = document.getElementById('transbordo-reserve');
            const reservedPanel = document.getElementById('transbordo-reserved');
            const reservationIdLabel = document.getElementById('transbordo-reservation-id');
            const confirmSend = document.getElementById('transbordo-confirm');
            const send = document.getElementById('transbordo-send');
            const homologationResult = document.getElementById('transbordo-homologation-result');
            let preparedShipments = [];
            let validated = false;
            let reservationId = null;
            let reservationAttempted = false;
            let sendAttempted = false;

            const customsField = (row, name) => row.querySelector('[data-customs="' + name + '"]');
            const renderCustoms = (shipmentsReady) => {
                preparedShipments = shipmentsReady;
                customsRows.replaceChildren();
                for (const shipment of shipmentsReady) {
                    const row = customsTemplate.content.firstElementChild.cloneNode(true);
                    row.dataset.shipmentId = String(shipment.shipment_id);
                    row.querySelector('[data-barge-name]').textContent =
                        'Envío ' + shipment.id_envio + ' · ' + shipment.shipment_number;
                    customsRows.appendChild(row);
                }
                homologation.classList.remove('hidden');
                validated = shipmentsReady.length >= 2;
                reserve.disabled = !validated || busy || frozen;
                homologationResult.textContent = validated
                    ? 'Completá los datos aduaneros de cada barcaza antes de crear la reserva.'
                    : 'Se necesitan por lo menos dos barcazas para homologación.';
            };

            const customsPayload = () => {
                const payload = {};
                for (const row of customsRows.querySelectorAll('.transbordo-customs-row')) {
                    const inputs = [...row.querySelectorAll('input[data-customs]')];
                    const invalid = inputs.find(input => !input.checkValidity());
                    if (invalid) {
                        invalid.reportValidity();
                        return null;
                    }
                    const value = name => customsField(row, name).value.trim();
                    payload[row.dataset.shipmentId] = {
                        idFiscalATAMIC: value('idFiscalATAMIC'),
                        lugOperOrigen: {
                            codLugOper: value('lugOperOrigen.codLugOper'),
                            codCiu: value('lugOperOrigen.codCiu').toUpperCase(),
                        },
                        lugOperDestino: {
                            codLugOper: value('lugOperDestino.codLugOper'),
                            codCiu: value('lugOperDestino.codCiu').toUpperCase(),
                        },
                    };
                }
                return payload;
            };
            @endif

            const setMessage = (text, error = false) => {
                message.textContent = text;
                message.className = 'mt-3 text-sm ' + (error ? 'text-red-700' : 'text-gray-700');
            };
            const invalidate = () => {
                if (frozen) return;
                dirty = true;
                result.classList.add('hidden');
                @if($canHomologate)
                validated = false;
                preparedShipments = [];
                customsRows.replaceChildren();
                homologation.classList.add('hidden');
                reservedPanel.classList.add('hidden');
                reserve.disabled = true;
                @endif
            };
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
                    const error = new Error(detail || json.message || `Error HTTP ${response.status}`);
                    error.status = response.status;
                    throw error;
                }
                return json;
            };
            const lock = (value) => {
                busy = value;
                save.disabled = value || frozen;
                preflight.disabled = value || frozen;
                add.disabled = value || frozen;
                @if($canHomologate)
                reserve.disabled = value || frozen || !validated || reservationAttempted;
                send.disabled = value || sendAttempted || !validated
                    || !reservationId || !confirmSend.checked;
                @endif
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
                    result.classList.add('hidden');
                    @if($canHomologate)
                    validated = false;
                    preparedShipments = [];
                    customsRows.replaceChildren();
                    homologation.classList.add('hidden');
                    reserve.disabled = true;
                    @endif
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
                    @if($canHomologate)
                    renderCustoms(data.distribution?.shipments ?? []);
                    @endif
                    setMessage('Distribución válida en la aplicación. Sin envío a ARCA.');
                } catch (error) {
                    result.classList.add('hidden');
                    @if($canHomologate)
                    homologation.classList.add('hidden');
                    validated = false;
                    @endif
                    setMessage(error.message, true);
                } finally { lock(false); }
            });
            @if($canHomologate)
            reserve.addEventListener('click', async () => {
                if (busy || frozen || dirty || !validated || reservationAttempted) return;
                const customsByShipmentId = customsPayload();
                if (!customsByShipmentId) return;
                reservationAttempted = true;
                lock(true);
                try {
                    const data = await request(routes.reserve, 'POST', { customsByShipmentId });
                    if (!Number.isInteger(data.transaction_record_id)
                        || data.transaction_record_id < 1
                        || data.transmitted !== false || data.status !== 'validating') {
                        throw new Error('La reserva no devolvió una confirmación verificable.');
                    }
                    reservationId = data.transaction_record_id;
                    reservationIdLabel.textContent = String(reservationId);
                    frozen = true;
                    for (const input of rows.querySelectorAll('input,select,button')) input.disabled = true;
                    for (const input of customsRows.querySelectorAll('input')) input.disabled = true;
                    reservedPanel.classList.remove('hidden');
                    homologationResult.textContent =
                        'Reserva creada sin transmisión. ID ' + reservationId + '. Revisá antes de confirmar.';
                    setMessage('La reserva está guardada. El reparto no puede modificarse.');
                } catch (error) {
                    // Una falla de conexión puede ocurrir DESPUÉS de guardar.
                    // Sólo 422 es un rechazo validado que permite corregir datos.
                    if (error.status === 422) reservationAttempted = false;
                    else frozen = true;
                    homologationResult.textContent = error.message +
                        (frozen ? ' No crees otra reserva ni recargues la página sin consultar el estado.' : '');
                    setMessage('No se pudo confirmar la reserva: ' + error.message, true);
                } finally { lock(false); }
            });

            confirmSend.addEventListener('change', () => lock(busy));
            send.addEventListener('click', async () => {
                if (busy || !validated || !reservationId
                    || !confirmSend.checked || sendAttempted) return;
                if (!window.confirm(
                    'Vas a transmitir este conocimiento y sus envíos a HOMOLOGACIÓN ARCA. ¿Confirmás el envío único?'
                )) return;
                // Se bloquea cualquier repetición incluso ante timeout, error 409
                // o pérdida de conexión; el servidor conserva la evidencia.
                sendAttempted = true;
                lock(true);
                try {
                    const data = await request(routes.send, 'POST', {
                        transaction_record_id: reservationId,
                        confirm: true,
                    });
                    if (data.success !== true) throw new Error('ARCA no confirmó la operación.');
                    if (!Array.isArray(data.tracks) || data.tracks.length !== preparedShipments.length) {
                        throw new Error('La respuesta no contiene un TRACK verificable por barcaza.');
                    }
                    homologationResult.replaceChildren();
                    const success = document.createElement('p');
                    success.textContent = 'Homologación aceptada. Reserva ' + reservationId + '.';
                    homologationResult.appendChild(success);
                    data.tracks.forEach((track, index) => {
                        const barge = preparedShipments[index];
                        const row = document.createElement('p');
                        row.textContent = 'Envío ' + barge.id_envio + ' · ' +
                            barge.shipment_number + ' · TRACK ARCA: ' + track;
                        homologationResult.appendChild(row);
                    });
                    setMessage('ARCA devolvió la respuesta. Revisá los TRACKs por barcaza.');
                } catch (error) {
                    homologationResult.textContent =
                        'Resultado sin confirmar para la reserva ' + reservationId + ': ' +
                        error.message + '. NO repitas el envío. Solicitá revisión técnica.';
                    setMessage('Resultado aduanero incierto. No se permite reenviar.', true);
                } finally { lock(false); }
            });

            // Recuperar desde BD después de recargar: nunca confiar en un
            // identificador guardado en el navegador ni generar otra reserva.
            const restoreHomologation = async (previous) => {
                if (!previous) return false;

                // Ante cualquier operación MIC/DTA previa, congelar primero.
                // Sólo un preflight NUEVO habilita continuar una reserva
                // pendiente que el servidor marcó expresamente como confirmable.
                frozen = true;
                reservationAttempted = true;
                sendAttempted = true;
                validated = false;
                preparedShipments = [];
                confirmSend.checked = false;
                reservationId = Number.isSafeInteger(previous.transaction_record_id)
                    && previous.transaction_record_id > 0
                    ? previous.transaction_record_id : null;

                for (const input of rows.querySelectorAll('input,select,button')) {
                    input.disabled = true;
                }
                reserve.disabled = true;
                homologation.classList.remove('hidden');
                reservedPanel.classList.toggle('hidden', !reservationId);
                reservationIdLabel.textContent = reservationId ? String(reservationId) : '';

                if (previous.status === 'success') {
                    homologationResult.textContent =
                        'La operación ya fue aceptada por ARCA. No se puede volver a enviar.';
                    setMessage('Transbordo registrado. No repetir la transmisión.');
                    return true;
                }
                if (previous.status !== 'validating'
                    || previous.can_confirm !== true || !reservationId) {
                    homologationResult.textContent =
                        'Existe una operación MIC/DTA anterior (estado: ' +
                        String(previous.status ?? 'desconocido') +
                        '). No se permite reservar ni reenviar. Solicitá revisión técnica.';
                    setMessage('Operación previa detectada: envío bloqueado.', true);
                    return true;
                }

                try {
                    // No llama a WSAA ni SOAP. Revalida el reparto ACTUAL
                    // antes de permitir confirmar una reserva persistida.
                    const ready = await request(routes.preflight, 'POST');
                    const shipmentsReady = ready.distribution?.shipments;
                    if (ready.valid !== true || ready.transmitted !== false
                        || !Array.isArray(shipmentsReady) || shipmentsReady.length < 2
                        || shipmentsReady.some(shipment => !Number.isSafeInteger(
                            shipment.shipment_id
                        ) || !Number.isSafeInteger(shipment.id_envio))) {
                        throw new Error('Distribución guardada no verificable.');
                    }
                    preparedShipments = shipmentsReady;
                    validated = true;
                    sendAttempted = false;
                    homologationResult.textContent =
                        'Reserva ' + reservationId +
                        ' recuperada sin reenviar. La distribución fue validada nuevamente.';
                    setMessage('Reserva pendiente recuperada. Revisá y confirmá solo si corresponde.');
                } catch (error) {
                    homologationResult.textContent =
                        'Reserva ' + reservationId +
                        ': no se pudo validar el reparto actual. ' + error.message +
                        '. No se permite enviar; solicitá revisión técnica.';
                    setMessage('Reserva encontrada, pero no habilitada para envío.', true);
                }
                return true;
            };
            @endif
            lock(true);
            request(routes.show, 'GET').then(async data => {
                (data.allocations?.length ? data.allocations : [{}]).forEach(addRow);
                dirty = false;
                @if($canHomologate)
                if (await restoreHomologation(data.homologation)) return;
                @endif
                setMessage('Distribución cargada. Revisá las asignaciones antes de guardarlas.');
            }).catch(error => {
                @if($canHomologate)
                // Sin consulta de estado no sabemos si ya se reservó/envió.
                // Bloquear acciones hasta recuperar la conexión y recargar.
                frozen = true;
                reservationAttempted = true;
                sendAttempted = true;
                @endif
                setMessage('No se pudo consultar el estado del viaje: ' + error.message +
                    '. Recargá cuando se restablezca la conexión. No repitas operaciones.', true);
            }).finally(() => lock(false));
        });
    </script>
</x-app-layout>
