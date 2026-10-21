{{-- ATA-DESC: alta física declarada, exclusivamente para un contenedor nuevo. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <nav aria-label="Ubicación" class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium text-gray-500">
                <a href="{{ route('company.dashboard') }}" class="hover:text-indigo-700 focus-visible:underline">Inicio</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.index') }}" class="hover:text-indigo-700 focus-visible:underline">Desconsolidados</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]) }}" class="hover:text-indigo-700 focus-visible:underline">Expediente</a>
                <span aria-hidden="true">/</span>
                <span class="text-gray-800">Registrar contenedor nuevo</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">Registrar contenedor nuevo</h1>
            <p class="mt-1 text-sm text-gray-500">Empresa: {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    @php
        $operationUrl = route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]);
        $existingUrl = route('company.simple.desconsolidado.operations.bills.items.containers.create', [
            'operation' => $operation->id,
            'bill' => $bill->id,
            'item' => $item->id,
        ]);

        // Valores tomados de los enums reales de containers; la primera
        // opción siempre permanece vacía para no asumir un estado operativo.
        $physicalStates = [
            ['value' => 'V', 'label' => 'V — Vacío'],
            ['value' => 'D', 'label' => 'D — Dañado'],
            ['value' => 'S', 'label' => 'S — Sucio'],
            ['value' => 'P', 'label' => 'P — Parcial'],
            ['value' => 'L', 'label' => 'L — Lleno'],
            ['value' => 'R', 'label' => 'R — Reparación'],
        ];
        $operationalStates = [
            ['value' => 'available', 'label' => 'Disponible'],
            ['value' => 'allocated', 'label' => 'Asignado'],
            ['value' => 'loading', 'label' => 'En carga'],
            ['value' => 'loaded', 'label' => 'Cargado'],
            ['value' => 'in_transit', 'label' => 'En tránsito'],
            ['value' => 'discharging', 'label' => 'En descarga'],
            ['value' => 'empty', 'label' => 'Vacío'],
            ['value' => 'maintenance', 'label' => 'Mantenimiento'],
            ['value' => 'inspection', 'label' => 'Inspección'],
            ['value' => 'quarantine', 'label' => 'Cuarentena'],
        ];
    @endphp

    <div class="min-h-screen bg-gray-50">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
            <a href="{{ $operationUrl }}"
               class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-gray-600 hover:text-indigo-700 focus-visible:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                </svg>
                Volver al expediente
            </a>

            <section aria-label="Identificación del conocimiento y la línea"
                     class="rounded-lg bg-gray-100 p-5 text-gray-800 shadow-lg sm:p-7">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-700">ATA-DESC · Contenedor nuevo</p>
                        <h2 class="mt-2 break-words text-2xl font-bold">Conocimiento {{ $bill->bill_number }}</h2>
                        <p class="mt-2 break-words text-sm text-gray-600">Título madre: {{ $operation->master_bill_number }}</p>
                        <p class="mt-1 text-xs text-gray-600">Viaje AFIP: {{ $operation->argentina_voyage_id }}</p>
                    </div>
                    <div class="shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-xs font-medium text-gray-600">Línea de mercadería</p>
                        <p class="mt-1 text-xl font-bold">N.º {{ $item->line_number }}</p>
                        <p class="mt-1 text-xs text-gray-700">
                            Embalaje {{ $item->packaging_code }} · Condición aduanera {{ $item->container_condition }}
                        </p>
                    </div>
                </div>
                <p class="mt-5 break-words border-t border-gray-200 pt-4 text-sm leading-6 text-gray-700">
                    {{ $item->item_description }}
                </p>
            </section>

            @if($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-900">
                    <p class="font-bold">No se pudo registrar el contenedor</p>
                    <p class="mt-1">Revisá los campos indicados. Los datos ingresados se conservan.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <form method="POST"
                      action="{{ route('company.simple.desconsolidado.operations.bills.items.containers.store-new', [
                          'operation' => $operation->id,
                          'bill' => $bill->id,
                          'item' => $item->id,
                      ]) }}"
                      class="space-y-6 lg:col-span-8" autocomplete="off">
                    @csrf

                    <section aria-labelledby="identity-heading" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">01 · Identificación</p>
                            <h2 id="identity-heading" class="mt-1 text-xl font-bold text-gray-900">Datos físicos declarados</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-500">
                                Este contenedor quedará registrado en el catálogo general y vinculado a la línea. Si ya está registrado, utilizá «Vincular contenedor existente».
                            </p>
                        </div>
                        <div class="space-y-5 p-5 sm:p-7">
                            <div>
                                <label for="container_number" class="block text-sm font-semibold text-gray-900">
                                    Número de contenedor <span class="text-red-600">*</span>
                                </label>
                                <input id="container_number" name="container_number" type="text" maxlength="15" required
                                       value="{{ old('container_number') }}" placeholder="Número real según documento"
                                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                                       aria-invalid="{{ $errors->has('container_number') ? 'true' : 'false' }}"
                                       class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 font-mono text-base uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                <p class="mt-1.5 text-xs leading-5 text-gray-500">
                                    Ingresá hasta 15 caracteres según la documentación. El sistema elimina los espacios iniciales y finales y convierte las letras a mayúsculas.
                                </p>
                                @error('container_number') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>
                            <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm leading-6 text-indigo-900">
                                <p class="font-bold">¿Este contenedor ya está registrado?</p>
                                <p class="mt-1">Podés asociarlo a esta línea desde «Vincular contenedor existente», conservando los datos de su ficha.</p>
                                <a href="{{ $existingUrl }}"
                                   class="mt-3 inline-flex min-h-11 items-center gap-2 font-bold text-indigo-800 underline decoration-indigo-300 underline-offset-4 hover:text-indigo-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    Vincular contenedor existente
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                                    </svg>
                                </a>
                            </div>
                            <div>
                                <label for="container_type_id" class="block text-sm font-semibold text-gray-900">
                                    Tipo de contenedor <span class="font-normal text-gray-500">(opcional)</span>
                                </label>
                                <select id="container_type_id" name="container_type_id"
                                        aria-invalid="{{ $errors->has('container_type_id') ? 'true' : 'false' }}"
                                        class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">No informado en la documentación</option>
                                    @foreach($containerTypes as $type)
                                        <option value="{{ $type->id }}" @selected((string) old('container_type_id') === (string) $type->id)>
                                            {{ $type->name }} ({{ $type->code }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1.5 text-xs text-gray-500">
                                    Seleccioná el tipo correspondiente si figura en la documentación. El catálogo muestra los tipos habilitados.
                                </p>
                                @error('container_type_id') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="condition" class="block text-sm font-semibold text-gray-900">
                                        Estado físico <span class="text-red-600">*</span>
                                    </label>
                                    <select id="condition" name="condition" required
                                            aria-invalid="{{ $errors->has('condition') ? 'true' : 'false' }}"
                                            class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Seleccioná el estado físico</option>
                                        @foreach($physicalStates as $state)
                                            <option value="{{ $state['value'] }}" @selected(old('condition') === $state['value'])>{{ $state['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs leading-5 text-gray-500">Este campo describe el estado físico del contenedor. La condición aduanera H/P se informa en la línea de mercadería.</p>
                                    @error('condition') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="operational_status" class="block text-sm font-semibold text-gray-900">
                                        Estado operativo <span class="text-red-600">*</span>
                                    </label>
                                    <select id="operational_status" name="operational_status" required
                                            aria-invalid="{{ $errors->has('operational_status') ? 'true' : 'false' }}"
                                            class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Seleccioná el estado operativo</option>
                                        @foreach($operationalStates as $state)
                                            <option value="{{ $state['value'] }}" @selected(old('operational_status') === $state['value'])>{{ $state['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs leading-5 text-gray-500">Seleccioná el estado operativo que corresponde al contenedor en el momento del registro.</p>
                                    @error('operational_status') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="tare_weight_kg" class="block text-sm font-semibold text-gray-900">
                                        Tara (kg) <span class="font-normal text-gray-500">(opcional)</span>
                                    </label>
                                    <input id="tare_weight_kg" name="tare_weight_kg" type="number" inputmode="decimal"
                                           min="0" max="999999.99" step="0.01" value="{{ old('tare_weight_kg') }}"
                                           placeholder="Peso declarado, si consta"
                                           aria-invalid="{{ $errors->has('tare_weight_kg') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs text-gray-500">Admite hasta dos decimales. Si no se conoce la tara, dejá el campo vacío en lugar de consignar cero.</p>
                                    @error('tare_weight_kg') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="max_gross_weight_kg" class="block text-sm font-semibold text-gray-900">
                                        Peso bruto máximo (kg) <span class="font-normal text-gray-500">(opcional)</span>
                                    </label>
                                    <input id="max_gross_weight_kg" name="max_gross_weight_kg" type="number" inputmode="decimal"
                                           min="0" max="999999.99" step="0.01" value="{{ old('max_gross_weight_kg') }}"
                                           placeholder="Peso máximo, si consta"
                                           aria-invalid="{{ $errors->has('max_gross_weight_kg') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs text-gray-500">Debe ser igual o mayor que la tara informada. Si no disponés del dato, podés dejar este campo vacío.</p>
                                    @error('max_gross_weight_kg') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="documents-heading" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">02 · Documentación aduanera</p>
                            <h2 id="documents-heading" class="mt-1 text-xl font-bold text-gray-900">Datos del contenedor para ATA-DESC</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-500">
                                Completá los datos disponibles en la documentación. Estos campos son opcionales para guardar el borrador; se revisarán antes de transmitir.
                            </p>
                        </div>
                        <div class="space-y-5 p-5 sm:p-7">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="argentina_container_code" class="block text-sm font-semibold text-gray-900">
                                        Características del contenedor (código AFIP)
                                    </label>
                                    <input id="argentina_container_code" name="argentina_container_code" type="text"
                                           value="{{ old('argentina_container_code') }}" maxlength="4"
                                           autocomplete="off" spellcheck="false" autocapitalize="characters"
                                           placeholder="Código declarado, si consta"
                                           aria-invalid="{{ $errors->has('argentina_container_code') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 font-mono text-base uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs text-gray-500">Ingresá el código de características declarado, si está disponible. Admite hasta 4 caracteres.</p>
                                    @error('argentina_container_code') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="shipper_seal" class="block text-sm font-semibold text-gray-900">
                                        Precinto del cargador (origen)
                                    </label>
                                    <input id="shipper_seal" name="shipper_seal" type="text"
                                           value="{{ old('shipper_seal') }}" maxlength="35"
                                           autocomplete="off" spellcheck="false"
                                           placeholder="Número de precinto, si está informado"
                                           aria-invalid="{{ $errors->has('shipper_seal') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs text-gray-500">Hasta 35 caracteres según el contrato de envío ATA-DESC.</p>
                                    @error('shipper_seal') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                                <p class="font-bold">Vencimiento CSC o código ACEP</p>
                                <p class="mt-1">
                                    Informá la fecha de vencimiento CSC o el código ACEP, según corresponda. No se admiten ambos simultáneamente.
                                    Si no disponés de ninguno, podés registrar el contenedor. Para transmitir, deberá existir una fecha de arribo documentada en el expediente que permita calcular el vencimiento sumando 480 días.
                                </p>
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="csc_expiry_date" class="block text-sm font-semibold text-gray-900">
                                        Fecha de vencimiento CSC <span class="font-normal text-gray-500">(opcional)</span>
                                    </label>
                                    <input id="csc_expiry_date" name="csc_expiry_date" type="date"
                                           value="{{ old('csc_expiry_date') }}"
                                           aria-invalid="{{ $errors->has('csc_expiry_date') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs text-gray-500">Ingresá la fecha de vencimiento indicada en la documentación CSC, si está disponible.</p>
                                    @error('csc_expiry_date') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="acep" class="block text-sm font-semibold text-gray-900">
                                        ACEP <span class="font-normal text-gray-500">(opcional)</span>
                                    </label>
                                    <input id="acep" name="acep" type="text" maxlength="20"
                                           value="{{ old('acep') }}" pattern="[A-Za-z0-9]+"
                                           autocomplete="off" spellcheck="false"
                                           placeholder="Código ACEP, si está informado"
                                           aria-invalid="{{ $errors->has('acep') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 font-mono text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs text-gray-500">Hasta 20 caracteres; únicamente letras y números.</p>
                                    @error('acep') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </section>

                    <section aria-label="Alcance del guardado" class="rounded-lg border border-indigo-100 bg-indigo-50 p-5 text-sm leading-6 text-indigo-900">
                        <p class="font-bold">El contenedor quedará registrado en el catálogo y vinculado a esta línea.</p>
                        <p class="mt-1">
                            En este paso no se asignan pesos ni cantidades por contenedor. La línea continúa como borrador y esta acción no transmite información a Aduana.
                        </p>
                    </section>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ $operationUrl }}"
                           class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Cancelar y volver
                        </a>
                        <button type="submit"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Registrar contenedor nuevo
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                            </svg>
                        </button>
                    </div>
                </form>

                <aside aria-label="Información importante" class="space-y-5 lg:col-span-4">
                    <section class="rounded-lg bg-gray-100 p-5 text-gray-800 shadow-lg sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-700">Antes del registro</p>
                        <h2 class="mt-2 text-xl font-bold">Revisión previa</h2>
                        <ol class="mt-5 space-y-4 text-sm text-gray-700">
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-700 font-bold text-white">1</span>
                                <span>Si el contenedor ya está registrado, utilizá «Vincular contenedor existente».</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-700 font-bold text-white">2</span>
                                <span>Seleccioná el estado físico y el operativo que correspondan al contenedor.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-700 font-bold text-white">3</span>
                                <span>Completá los datos documentales disponibles, incluido CSC o ACEP cuando corresponda.</span>
                            </li>
                        </ol>
                        <p class="mt-6 border-t border-gray-200 pt-5 text-xs leading-6 text-gray-600">
                            La condición aduanera H/P será la definida en la línea de mercadería. Los demás contenedores no se modifican.
                        </p>
                    </section>
                    <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                        <p class="font-bold">El registro no realiza envíos a Aduana.</p>
                        <p class="mt-2">
                            Antes de transmitir, la operación deberá superar las validaciones documentales y aduaneras correspondientes. Este formulario solo registra el contenedor y lo vincula a la línea.
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
