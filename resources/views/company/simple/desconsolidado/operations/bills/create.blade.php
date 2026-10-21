{{-- ATA-DESC: alta de conocimiento hijo, sin viaje ni embarcación local. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <nav aria-label="Ubicación" class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium text-gray-500">
                <a href="{{ route('company.dashboard') }}" class="hover:text-indigo-700">Inicio</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.index') }}" class="hover:text-indigo-700">Desconsolidados</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]) }}" class="hover:text-indigo-700">Expediente</a>
                <span aria-hidden="true">/</span>
                <span class="text-gray-900">Conocimiento hijo</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">{{ ($editingBill ?? null) ? 'Editar conocimiento hijo' : 'Agregar conocimiento hijo' }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $company->legal_name }}</p>
        </div>
    </x-slot>

    @php
        // Se reutilizan los buscadores reales; nunca se precarga el catálogo de puertos/clientes.
        $editingBill = $editingBill ?? null;
        $clientSearchUrl = route('company.clients.search');
        $portSearchUrl = route('company.bills-of-lading.ports.search');
        $operationUrl = route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]);
        $lookupFields = [
            ['name' => 'shipper_id', 'label' => 'Cargador / remitente', 'help' => 'Buscá por nombre o identificación fiscal y seleccioná el cargador que figure en el conocimiento.', 'kind' => 'client', 'url' => $clientSearchUrl, 'placeholder' => 'Nombre o identificación fiscal del cargador'],
            ['name' => 'consignee_id', 'label' => 'Consignatario', 'help' => 'Buscá por nombre o identificación fiscal y seleccioná el consignatario que figure en el conocimiento.', 'kind' => 'client', 'url' => $clientSearchUrl, 'placeholder' => 'Nombre o identificación fiscal del consignatario'],
            ['name' => 'loading_port_id', 'label' => 'Puerto de embarque del conocimiento hijo', 'help' => 'Buscá el puerto de este conocimiento; puede diferir del puerto del título madre.', 'kind' => 'port', 'url' => $portSearchUrl, 'placeholder' => 'Buscá por nombre, ciudad o código de puerto'],
            ['name' => 'discharge_port_id', 'label' => 'Puerto de descarga en Argentina', 'help' => 'Elegí el puerto argentino donde se descargará la mercadería.', 'kind' => 'port', 'url' => $portSearchUrl, 'placeholder' => 'Buscá el puerto argentino de descarga'],
        ];
    @endphp

    <div class="min-h-screen bg-gray-50">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">

            <a href="{{ $operationUrl }}" class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-gray-600 hover:text-indigo-700 focus-visible:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                Volver al expediente
            </a>

            <section aria-label="Título madre" class="rounded-lg bg-gray-100 p-5 text-gray-800 shadow-md sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Expediente de desconsolidación</p>
                        <h2 class="mt-1 break-words text-xl font-bold">{{ $operation->master_bill_number }}</h2>
                        <p class="mt-2 text-sm text-gray-600">
                            Viaje AFIP: <span class="font-semibold text-gray-900">{{ $operation->argentina_voyage_id }}</span>
                            <span class="mx-1.5 text-gray-500">·</span>
                            Puerto madre: <span class="font-semibold text-gray-900">{{ $operation->master_loading_port_code }}</span>
                        </p>
                    </div>
                    <span class="inline-flex w-fit shrink-0 items-center rounded-lg bg-white px-3 py-2 text-xs font-semibold text-indigo-700">
                        PASO 2 · CONOCIMIENTO HIJO
                    </span>
                </div>
            </section>

            @if($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900">
                    <p class="font-bold">Hay datos para revisar</p>
                    <p class="mt-1">Revisá las observaciones indicadas. Los datos ingresados se conservan.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <form id="ata-desc-house-form" action="{{ ($editingBill ? route('company.simple.desconsolidado.operations.bills.update', ['operation' => $operation->id, 'bill' => $editingBill->id]) : route('company.simple.desconsolidado.operations.bills.store', ['operation' => $operation->id])) }}"
                      method="POST" class="space-y-6 lg:col-span-8" autocomplete="off">
                    @csrf
                    @if($editingBill) @method('PUT') @endif

                    <section aria-labelledby="identity-heading" class="overflow-visible rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">01 · Identificación</p>
                            <h2 id="identity-heading" class="mt-1 text-xl font-bold text-gray-900">Datos del conocimiento</h2>
                            <p class="mt-1 text-sm text-gray-500">Ingresá el número del conocimiento y seleccioná el cargador y el consignatario indicados en la documentación.</p>
                        </div>
                        <div class="space-y-5 px-5 py-6 sm:px-7">
                            <div>
                                <label for="bill_number" class="block text-sm font-semibold text-gray-900">
                                    Número del conocimiento hijo <span class="text-red-600">*</span>
                                </label>
                                <input id="bill_number" name="bill_number" type="text" maxlength="18" required
                                       value="{{ old('bill_number', $editingBill?->bill_number) }}" placeholder="Número tal como figura en el BL"
                                       aria-invalid="{{ $errors->has('bill_number') ? 'true' : 'false' }}"
                                       class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                <p class="mt-1.5 text-xs text-gray-500">Hasta 18 caracteres. El conocimiento madre ya está asociado a este expediente.</p>
                                @error('bill_number') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="loading_date" class="block text-sm font-semibold text-gray-900">
                                    Fecha de embarque del conocimiento hijo
                                </label>
                                <input id="loading_date" name="loading_date" type="date"
                                       value="{{ old('loading_date', $editingBill?->loading_date?->format('Y-m-d')) }}"
                                       aria-invalid="{{ $errors->has('loading_date') ? 'true' : 'false' }}"
                                       class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                <p class="mt-1.5 text-xs text-gray-600">
                                    Ingresá la fecha de embarque indicada en el conocimiento. Es necesaria antes de enviar a Aduana
                                    y es distinta de la fecha de arribo del viaje. Podés guardarla después mientras el conocimiento sea editable.
                                </p>
                                @error('loading_date') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>

                            @foreach($lookupFields as $field)
                                @if($field['kind'] === 'client')
                                    <div class="relative" data-desc-lookup data-kind="{{ $field['kind'] }}"
                                         data-search-url="{{ $field['url'] }}" data-field-name="{{ $field['name'] }}">
                                        <label for="{{ $field['name'] }}_search" class="block text-sm font-semibold text-gray-900">
                                            {{ $field['label'] }} <span class="text-red-600">*</span>
                                        </label>
                                        <p id="{{ $field['name'] }}_help" class="mt-1 text-xs leading-5 text-gray-500">{{ $field['help'] }}</p>
                                        <input type="search" id="{{ $field['name'] }}_search" name="{{ $field['name'] }}_search"
                                               value="{{ old($field['name'] . '_search', $editingBill ? match($field['name']) { 'shipper_id' => $editingBill->shipper?->legal_name, 'consignee_id' => $editingBill->consignee?->legal_name, 'loading_port_id' => $editingBill->loadingPort?->name, 'discharge_port_id' => $editingBill->dischargePort?->name, default => null } : null) }}"
                                               role="combobox" aria-autocomplete="list" aria-expanded="false"
                                               aria-controls="{{ $field['name'] }}_options"
                                               aria-describedby="{{ $field['name'] }}_help"
                                               aria-invalid="{{ $errors->has($field['name']) ? 'true' : 'false' }}"
                                               placeholder="{{ $field['placeholder'] }}" required autocomplete="off"
                                               class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                               data-lookup-input/>
                                        <input type="hidden" name="{{ $field['name'] }}" value="{{ old($field['name'], $editingBill?->{$field['name']}) }}" data-lookup-id/>
                                        <div id="{{ $field['name'] }}_options" role="listbox"
                                             aria-label="Resultados para {{ $field['label'] }}"
                                             class="absolute z-30 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-xl"
                                             data-lookup-results></div>
                                        <p class="mt-1.5 text-xs text-gray-500" data-lookup-hint>Ingresá al menos 2 caracteres y seleccioná una opción de la lista.</p>
                                        @error($field['name']) <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>

                    <section aria-labelledby="route-heading" class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">02 · Recorrido</p>
                            <h2 id="route-heading" class="mt-1 text-xl font-bold text-gray-900">Puertos del conocimiento hijo</h2>
                            <p class="mt-1 text-sm text-gray-500">Buscá cada puerto por nombre, ciudad o código y seleccioná la opción correspondiente.</p>
                        </div>
                        <div class="space-y-5 px-5 py-6 sm:px-7">
                            @foreach($lookupFields as $field)
                                @if($field['kind'] === 'port')
                                    <div class="relative" data-desc-lookup data-kind="{{ $field['kind'] }}"
                                         data-search-url="{{ $field['url'] }}" data-field-name="{{ $field['name'] }}">
                                        <label for="{{ $field['name'] }}_search" class="block text-sm font-semibold text-gray-900">
                                            {{ $field['label'] }} <span class="text-red-600">*</span>
                                        </label>
                                        <p id="{{ $field['name'] }}_help" class="mt-1 text-xs leading-5 text-gray-500">{{ $field['help'] }}</p>
                                        <input type="search" id="{{ $field['name'] }}_search" name="{{ $field['name'] }}_search"
                                               value="{{ old($field['name'] . '_search', $editingBill ? match($field['name']) { 'shipper_id' => $editingBill->shipper?->legal_name, 'consignee_id' => $editingBill->consignee?->legal_name, 'loading_port_id' => $editingBill->loadingPort?->name, 'discharge_port_id' => $editingBill->dischargePort?->name, default => null } : null) }}"
                                               role="combobox" aria-autocomplete="list" aria-expanded="false"
                                               aria-controls="{{ $field['name'] }}_options"
                                               aria-describedby="{{ $field['name'] }}_help"
                                               aria-invalid="{{ $errors->has($field['name']) ? 'true' : 'false' }}"
                                               placeholder="{{ $field['placeholder'] }}" required autocomplete="off"
                                               class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                               data-lookup-input/>
                                        <input type="hidden" name="{{ $field['name'] }}" value="{{ old($field['name'], $editingBill?->{$field['name']}) }}" data-lookup-id/>
                                        <div id="{{ $field['name'] }}_options" role="listbox"
                                             aria-label="Resultados para {{ $field['label'] }}"
                                             class="absolute z-30 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-xl"
                                             data-lookup-results></div>
                                        <p class="mt-1.5 text-xs text-gray-500" data-lookup-hint>Ingresá al menos 2 caracteres y seleccioná una opción de la lista.</p>
                                        @error($field['name']) <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>

                    <section aria-labelledby="cargo-heading" class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">03 · Mercadería</p>
                            <h2 id="cargo-heading" class="mt-1 text-xl font-bold text-gray-900">Datos generales de la carga</h2>
                            <p class="mt-1 text-sm text-gray-500">Estos datos pertenecen al conocimiento hijo. Las líneas de mercadería se completan por separado.</p>
                        </div>
                        <div class="space-y-5 px-5 py-6 sm:px-7">
                            <div>
                                <label for="primary_cargo_type_id" class="block text-sm font-semibold text-gray-900">Tipo de carga <span class="text-red-600">*</span></label>
                                <select id="primary_cargo_type_id" name="primary_cargo_type_id" required
                                        class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Seleccioná el tipo de carga</option>
                                    @foreach($cargoTypes as $type)
                                        <option value="{{ $type->id }}" @selected((string) old('primary_cargo_type_id', $editingBill?->primary_cargo_type_id) === (string) $type->id)>{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                @error('primary_cargo_type_id') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                @if($cargoTypes->isEmpty())
                                    <p role="alert" class="mt-2 text-sm font-medium text-amber-800">No hay tipos de carga habilitados para desconsolidación. Contactá al administrador antes de continuar.</p>
                                @endif
                            </div>

                            <div>
                                <label for="gross_weight_kg" class="block text-sm font-semibold text-gray-900">Peso bruto declarado (kg) <span class="text-red-600">*</span></label>
                                <input id="gross_weight_kg" name="gross_weight_kg" type="number" inputmode="decimal"
                                       min="0" max="9999999999.99" step="0.01" required value="{{ old('gross_weight_kg', $editingBill?->gross_weight_kg) }}"
                                       placeholder="Ej.: 125.75" class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                <p class="mt-1.5 text-xs text-gray-500">Usá hasta dos decimales, con punto (por ejemplo, 125.75), según el peso declarado.</p>
                                @error('gross_weight_kg') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="cargo_description" class="block text-sm font-semibold text-gray-900">Descripción de la mercadería <span class="text-red-600">*</span></label>
                                <textarea id="cargo_description" name="cargo_description" rows="4" required
                                          placeholder="Describí la mercadería según el conocimiento"
                                          class="mt-2 block w-full rounded-xl border-gray-300 px-4 py-3 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('cargo_description', $editingBill?->cargo_description) }}</textarea>
                                @error('cargo_description') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="cargo_marks" class="block text-sm font-semibold text-gray-900">Marcas de los bultos <span class="text-red-600">*</span></label>
                                <input id="cargo_marks" name="cargo_marks" type="text" maxlength="80" required value="{{ old('cargo_marks', $editingBill?->cargo_marks) }}"
                                       placeholder="Marcas como figuran en la documentación"
                                       class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                <p class="mt-1.5 text-xs text-gray-500">Hasta 80 caracteres.</p>
                                @error('cargo_marks') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="is_consolidated" class="block text-sm font-semibold text-gray-900">¿El conocimiento es consolidado? <span class="text-red-600">*</span></label>
                                    <select id="is_consolidated" name="is_consolidated" required
                                            class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Elegí una respuesta</option>
                                        <option value="S" @selected(old('is_consolidated', $editingBill ? ((int) $editingBill->getRawOriginal('is_consolidated') === 1 ? 'S' : 'N') : null) === 'S')>Sí</option>
                                        <option value="N" @selected(old('is_consolidated', $editingBill ? ((int) $editingBill->getRawOriginal('is_consolidated') === 1 ? 'S' : 'N') : null) === 'N')>No</option>
                                    </select>
                                    @error('is_consolidated') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="is_transit_transshipment" class="block text-sm font-semibold text-gray-900">¿Corresponde tránsito o transbordo? <span class="text-red-600">*</span></label>
                                    <select id="is_transit_transshipment" name="is_transit_transshipment" required
                                            class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Elegí una respuesta</option>
                                        <option value="S" @selected(old('is_transit_transshipment', $editingBill?->is_transit_transshipment) === 'S')>Sí</option>
                                        <option value="N" @selected(old('is_transit_transshipment', $editingBill?->is_transit_transshipment) === 'N')>No</option>
                                    </select>
                                    @error('is_transit_transshipment') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-5 text-sm leading-6 text-indigo-900">
                        <p class="font-bold">Este paso solo guarda un borrador.</p>
                        <p class="mt-1">Todavía habrá que cargar las líneas de mercadería y, cuando corresponda, los contenedores. Guardar el conocimiento no transmite información a Aduana.</p>
                    </div>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ $operationUrl }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancelar y volver</a>
                        <button type="submit" @disabled($cargoTypes->isEmpty())
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:bg-gray-400">
                            Guardar conocimiento hijo
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </button>
                    </div>
                </form>

                <aside aria-label="Guía" class="space-y-5 lg:col-span-4">
                    <section class="rounded-lg bg-gray-100 p-5 text-gray-800 shadow-lg sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-700">Guía rápida</p>
                        <h2 class="mt-2 text-xl font-bold">¿Qué se guarda ahora?</h2>
                        <ol class="mt-5 space-y-4 text-sm text-gray-700">
                            <li class="flex items-start gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-700 font-bold text-white">1</span><span>El número del conocimiento hijo y las partes involucradas.</span></li>
                            <li class="flex items-start gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-700 font-bold text-white">2</span><span>Los puertos propios de ese conocimiento.</span></li>
                            <li class="flex items-start gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-700 font-bold text-white">3</span><span>Los datos generales de mercadería declarados.</span></li>
                        </ol>
                        <p class="mt-6 border-t border-gray-200 pt-5 text-xs leading-6 text-gray-600">Después del guardado regresarás al expediente. El conocimiento todavía no estará listo para transmitir.</p>
                    </section>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-600">
                        <p class="font-bold text-gray-900">¿No encontrás una empresa o un puerto?</p>
                        <p class="mt-2 leading-6">Buscá por nombre o código. Si no encontrás el registro que necesitás, verificá los datos en el catálogo correspondiente antes de continuar.</p>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
        (function () {
            'use strict';
            const form = document.getElementById('ata-desc-house-form');
            if (!form) return;

            const lookups = Array.from(form.querySelectorAll('[data-desc-lookup]'));

            lookups.forEach(function (wrapper) {
                const input = wrapper.querySelector('[data-lookup-input]');
                const hidden = wrapper.querySelector('[data-lookup-id]');
                const results = wrapper.querySelector('[data-lookup-results]');
                const hint = wrapper.querySelector('[data-lookup-hint]');
                const kind = wrapper.dataset.kind;
                const url = wrapper.dataset.searchUrl;
                let timer = null;
                let controller = null;
                let sequence = 0;
                let options = [];
                let active = -1;

                function close() {
                    results.classList.add('hidden');
                    results.replaceChildren();
                    input.setAttribute('aria-expanded', 'false');
                    input.removeAttribute('aria-activedescendant');
                    options = [];
                    active = -1;
                }

                function message(text) {
                    results.replaceChildren();
                    const row = document.createElement('p');
                    row.className = 'px-4 py-3 text-sm text-gray-600';
                    row.setAttribute('role', 'status');
                    row.textContent = text;
                    results.appendChild(row);
                    results.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                    options = [];
                    active = -1;
                }

                function setActive(index) {
                    if (!options.length) return;
                    active = (index + options.length) % options.length;
                    Array.from(results.querySelectorAll('[role="option"]')).forEach(function (node, i) {
                        const highlighted = i === active;
                        node.classList.toggle('bg-indigo-50', highlighted);
                        node.setAttribute('aria-selected', highlighted ? 'true' : 'false');
                    });
                    const node = results.querySelector('[data-result-index="' + active + '"]');
                    if (node) {
                        input.setAttribute('aria-activedescendant', node.id);
                        node.scrollIntoView({ block: 'nearest' });
                    }
                }

                function choose(item) {
                    hidden.value = String(item.id);
                    input.value = item.label;
                    input.setCustomValidity('');
                    hint.textContent = 'Seleccionado: ' + item.label;
                    hint.classList.remove('text-red-700');
                    close();
                    input.focus();
                }

                function draw(items) {
                    if (!items.length) {
                        message('No encontramos coincidencias. Probá con otro nombre o código.');
                        return;
                    }
                    results.replaceChildren();
                    options = items;
                    active = -1;
                    items.forEach(function (item, index) {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.id = wrapper.dataset.fieldName + '_option_' + index;
                        option.dataset.resultIndex = String(index);
                        option.setAttribute('role', 'option');
                        option.setAttribute('aria-selected', 'false');
                        option.className = 'block w-full px-4 py-3 text-left text-sm text-gray-900 hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none';
                        option.textContent = item.label;
                        option.addEventListener('click', function () { choose(item); });
                        results.appendChild(option);
                    });
                    results.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                }

                function cancel() {
                    clearTimeout(timer);
                    sequence++;
                    if (controller) controller.abort();
                    controller = null;
                }

                function schedule() {
                    cancel();
                    close();
                    if (hidden.value) return;
                    const term = input.value.trim();
                    if (term.length < 2) {
                        hint.textContent = 'Ingresá al menos 2 caracteres y seleccioná una opción de la lista.';
                        return;
                    }
                    const current = sequence;
                    timer = setTimeout(async function () {
                        controller = new AbortController();
                        const currentController = controller;
                        message(kind === 'client' ? 'Buscando clientes...' : 'Buscando puertos...');
                        try {
                            const target = new URL(url, window.location.origin);
                            target.searchParams.set('q', term);
                            const response = await fetch(target.toString(), {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                                signal: currentController.signal
                            });
                            if (!response.ok) throw new Error('HTTP ' + response.status);
                            const payload = await response.json();
                            if (sequence !== current || input.value.trim() !== term) return;
                            const source = kind === 'client'
                                ? (Array.isArray(payload) ? payload : [])
                                : (Array.isArray(payload.results) ? payload.results : []);
                            draw(source.filter(function (entry) { return entry && entry.id; }).map(function (entry) {
                                const label = kind === 'client'
                                    ? String(entry.legal_name || '') + (entry.tax_id ? ' · Identificación fiscal: ' + entry.tax_id : '')
                                    : String(entry.label || entry.code || '') + (entry.city ? ' · ' + entry.city : '');
                                return { id: entry.id, label: label };
                            }).filter(function (entry) { return entry.label.trim() !== ''; }));
                        } catch (error) {
                            if (error.name !== 'AbortError' && sequence === current) {
                                message('No se pudieron obtener resultados en este momento. Intentá nuevamente; si el problema continúa, contactá al administrador.');
                            }
                        } finally {
                            if (controller === currentController) controller = null;
                        }
                    }, 280);
                }

                input.addEventListener('input', function () {
                    hidden.value = '';
                    input.setCustomValidity('');
                    hint.classList.remove('text-red-700');
                    schedule();
                });
                input.addEventListener('focus', function () {
                    if (!hidden.value && input.value.trim().length >= 2) schedule();
                });
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        cancel();
                        close();
                    } else if (event.key === 'ArrowDown' && options.length) {
                        event.preventDefault();
                        setActive(active + 1);
                    } else if (event.key === 'ArrowUp' && options.length) {
                        event.preventDefault();
                        setActive(active - 1);
                    } else if (event.key === 'Enter' && options.length && active >= 0) {
                        event.preventDefault();
                        choose(options[active]);
                    }
                });
                document.addEventListener('pointerdown', function (event) {
                    if (!wrapper.contains(event.target)) close();
                });
            });

            form.addEventListener('submit', function (event) {
                for (const wrapper of lookups) {
                    const input = wrapper.querySelector('[data-lookup-input]');
                    const hidden = wrapper.querySelector('[data-lookup-id]');
                    const hint = wrapper.querySelector('[data-lookup-hint]');
                    if (!hidden.value) {
                        event.preventDefault();
                        input.setCustomValidity('Para continuar, seleccioná una opción de la lista de resultados.');
                        hint.textContent = 'Seleccioná una opción de los resultados para continuar.';
                        hint.classList.add('text-red-700');
                        input.reportValidity();
                        input.focus();
                        return;
                    }
                }
            });
        })();
        </script>
    @endpush
</x-app-layout>
