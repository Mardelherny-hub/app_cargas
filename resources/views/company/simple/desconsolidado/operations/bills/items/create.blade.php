{{-- ATA-DESC: una línea de mercadería del conocimiento hijo, sin datos ni contenedores ficticios. --}}
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
                <span class="text-gray-800">Nueva línea de mercadería</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">{{ $editingItem ? 'Editar línea de mercadería' : 'Agregar línea de mercadería' }}</h1>
            <p class="mt-1 text-sm text-gray-500">Empresa: {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    @php
        // La cabecera guarda un booleano real, aunque el modelo lo exponga como cadena.
        $editingItem = $editingItem ?? null;
        $isNonConsolidated = (int) $bill->getRawOriginal('is_consolidated') !== 1;
        $operationUrl = route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]);
        $indicators = [
            ['name' => 'is_secure_logistics_operator', 'label' => '¿Operador logístico seguro?'],
            ['name' => 'is_monitored_transit', 'label' => '¿Tránsito monitoreado?'],
            ['name' => 'is_renar', 'label' => '¿Corresponde RENAR?'],
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

            <section aria-label="Conocimiento que estás completando"
                     class="relative overflow-hidden rounded-lg bg-gray-100 p-5 text-gray-800 shadow-lg sm:p-7">
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-700">ATA-DESC · Paso 3: mercadería</p>
                        <h2 class="mt-2 break-words text-2xl font-bold">{{ $bill->bill_number }}</h2>
                        <p class="mt-2 break-words text-sm text-gray-600">
                            Conocimiento madre: <span class="font-semibold text-gray-900">{{ $operation->master_bill_number }}</span>
                        </p>
                        <p class="mt-1 text-xs text-gray-600">Viaje AFIP: {{ $operation->argentina_voyage_id }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 sm:text-right">
                        <p class="text-xs font-medium text-gray-600">Peso bruto declarado del conocimiento</p>
                        <p class="mt-1 text-xl font-bold">{{ $bill->gross_weight_kg }} <span class="text-sm font-medium">kg</span></p>
                        <p class="mt-1 text-xs text-gray-600">No se modifica al guardar esta línea.</p>
                    </div>
                </div>
            </section>

            @if($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900">
                    <p class="font-bold">Revisá estos datos antes de continuar</p>
                    <p class="mt-1 text-red-700">Lo que escribiste se conserva. Corregí los campos indicados.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <form method="POST"
                      action="{{ $editingItem ? route('company.simple.desconsolidado.operations.bills.items.update', ['operation' => $operation->id, 'bill' => $bill->id, 'item' => $editingItem->id]) : route('company.simple.desconsolidado.operations.bills.items.store', ['operation' => $operation->id, 'bill' => $bill->id]) }}"
                      class="space-y-6 lg:col-span-8" autocomplete="off">
                    @csrf
                    @if($editingItem) @method('PUT') @endif

                    <section aria-labelledby="line-heading" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">01 · Identificación de la línea</p>
                            <h2 id="line-heading" class="mt-1 text-xl font-bold text-gray-900">¿Qué mercadería contiene?</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-500">Ingresá los datos reales de esta línea del conocimiento hijo.</p>
                        </div>

                        <div class="space-y-5 p-5 sm:p-7">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="line_number" class="block text-sm font-semibold text-gray-900">
                                        Número de línea <span class="text-red-600">*</span>
                                    </label>
                                    <input type="number" id="line_number" name="line_number"
                                           value="{{ old('line_number', $editingItem?->line_number) }}" min="1" max="999" step="1" required
                                           placeholder="Número del documento"
                                           aria-invalid="{{ $errors->has('line_number') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs text-gray-500">Entre 1 y 999; no puede repetirse en este conocimiento.</p>
                                    @error('line_number') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="cargo_type_id" class="block text-sm font-semibold text-gray-900">
                                        Tipo de carga <span class="text-red-600">*</span>
                                    </label>
                                    <select id="cargo_type_id" name="cargo_type_id" required
                                            aria-invalid="{{ $errors->has('cargo_type_id') ? 'true' : 'false' }}"
                                            class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Seleccioná el tipo declarado</option>
                                        @foreach($cargoTypes as $type)
                                            <option value="{{ $type->id }}" @selected((string) old('cargo_type_id', $editingItem?->cargo_type_id) === (string) $type->id)>{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs text-gray-500">Solo tipos habilitados para desconsolidación.</p>
                                    @error('cargo_type_id') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                    @if($cargoTypes->isEmpty())
                                        <p role="alert" class="mt-2 text-sm font-medium text-amber-800">No hay tipos de carga habilitados. Consultá al administrador antes de continuar.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="packaging_code" class="block text-sm font-semibold text-gray-900">
                                        Código de embalaje ATA-DESC <span class="text-red-600">*</span>
                                    </label>
                                    <input type="text" id="packaging_code" name="packaging_code"
                                           value="{{ old('packaging_code', $editingItem?->packaging_code) }}" minlength="2" maxlength="2" required
                                           autocomplete="off" spellcheck="false" autocapitalize="characters"
                                           placeholder="Código de 2 caracteres"
                                           aria-invalid="{{ $errors->has('packaging_code') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 font-mono text-base uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs leading-5 text-gray-500">No inventes un código. <strong>05</strong> corresponde a carga contenedorizada; requerirá vincular los contenedores reales.</p>
                                    @error('packaging_code') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="container_condition" class="block text-sm font-semibold text-gray-900">
                                        Condición H / P <span class="text-red-600">*</span>
                                    </label>
                                    <select id="container_condition" name="container_condition" required
                                            aria-invalid="{{ $errors->has('container_condition') ? 'true' : 'false' }}"
                                            class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Elegí la condición declarada</option>
                                        <option value="H" @selected(old('container_condition', $editingItem?->container_condition) === 'H')>H</option>
                                        <option value="P" @selected(old('container_condition', $editingItem?->container_condition) === 'P')>P</option>
                                    </select>
                                    <p class="mt-1.5 text-xs leading-5 text-gray-500">Debe coincidir con la condición informada para la línea y, si aplica, sus contenedores.</p>
                                    @error('container_condition') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="package_quantity" class="block text-sm font-semibold text-gray-900">
                                        Cantidad de bultos declarada <span class="text-red-600">*</span>
                                    </label>
                                    <input type="number" id="package_quantity" name="package_quantity"
                                           value="{{ old('package_quantity', $editingItem?->package_quantity) }}" min="1" max="999999999" step="1" required
                                           placeholder="Cantidad del documento"
                                           aria-invalid="{{ $errors->has('package_quantity') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs leading-5 text-gray-500">Si el embalaje es 05, la cantidad manifestada a Aduana se calcula después con los contenedores vinculados.</p>
                                    @error('package_quantity') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="gross_weight_kg" class="block text-sm font-semibold text-gray-900">
                                        Peso bruto de esta línea (kg) <span class="text-red-600">*</span>
                                    </label>
                                    <input type="number" id="gross_weight_kg" name="gross_weight_kg"
                                           value="{{ old('gross_weight_kg', $editingItem?->gross_weight_kg) }}" min="0" max="9999999999.99" step="0.01" inputmode="decimal" required
                                           placeholder="Ej.: 40.25"
                                           aria-invalid="{{ $errors->has('gross_weight_kg') ? 'true' : 'false' }}"
                                           class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                    <p class="mt-1.5 text-xs text-gray-500">Hasta 2 decimales, con punto. No se recalcula el peso total del BL.</p>
                                    @error('gross_weight_kg') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label for="item_description" class="block text-sm font-semibold text-gray-900">
                                    Descripción de la mercadería <span class="text-red-600">*</span>
                                </label>
                                <textarea id="item_description" name="item_description" rows="3" maxlength="80" required
                                          aria-invalid="{{ $errors->has('item_description') ? 'true' : 'false' }}"
                                          placeholder="Descripción de esta línea según la documentación"
                                          class="mt-2 block w-full rounded-xl border-gray-300 px-4 py-3 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('item_description', $editingItem?->item_description) }}</textarea>
                                <p class="mt-1.5 text-xs text-gray-500">Máximo 80 caracteres por línea, según el contrato ATA-DESC.</p>
                                @error('item_description') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="cargo_marks" class="block text-sm font-semibold text-gray-900">
                                    Marcas o números de los bultos <span class="text-red-600">*</span>
                                </label>
                                <input type="text" id="cargo_marks" name="cargo_marks" maxlength="100" required
                                       value="{{ old('cargo_marks', $editingItem?->cargo_marks) }}"
                                       placeholder="Marcas tal como figuran en el documento"
                                       aria-invalid="{{ $errors->has('cargo_marks') ? 'true' : 'false' }}"
                                       class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                <p class="mt-1.5 text-xs text-gray-500">Máximo 100 caracteres.</p>
                                @error('cargo_marks') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="customs-heading" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">02 · Datos aduaneros</p>
                            <h2 id="customs-heading" class="mt-1 text-xl font-bold text-gray-900">Clasificación y controles</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-500">
                                La posición arancelaria y las tres respuestas deben ser coherentes en todas las líneas de este conocimiento.
                            </p>
                        </div>
                        <div class="space-y-5 p-5 sm:p-7">
                            <div>
                                <label for="tariff_position" class="block text-sm font-semibold text-gray-900">
                                    Posición arancelaria declarada (NCM) <span class="text-red-600">*</span>
                                </label>
                                <input type="text" id="tariff_position" name="tariff_position"
                                       value="{{ old('tariff_position', $editingItem?->tariff_position) }}" maxlength="{{ $isNonConsolidated ? 15 : 16 }}"
                                       @if($isNonConsolidated) minlength="7" @endif
                                       autocomplete="off" spellcheck="false" required
                                       placeholder="Posición indicada en el documento"
                                       aria-invalid="{{ $errors->has('tariff_position') ? 'true' : 'false' }}"
                                       class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 px-4 font-mono text-base uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                                <p class="mt-1.5 text-xs text-gray-500">
                                    @if($isNonConsolidated)
                                        Conocimiento no consolidado: entre 7 y 15 caracteres.
                                    @else
                                        Conocimiento consolidado: hasta 16 caracteres.
                                    @endif
                                    Si ya existen líneas, debe coincidir con la posición declarada en ellas.
                                </p>
                                @error('tariff_position') <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                @foreach($indicators as $indicator)
                                    <div>
                                        <label for="{{ $indicator['name'] }}" class="block text-sm font-semibold text-gray-900">
                                            {{ $indicator['label'] }} <span class="text-red-600">*</span>
                                        </label>
                                        <select id="{{ $indicator['name'] }}" name="{{ $indicator['name'] }}" required
                                                aria-invalid="{{ $errors->has($indicator['name']) ? 'true' : 'false' }}"
                                                class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">Elegí una respuesta</option>
                                            <option value="S" @selected(old($indicator['name']) === 'S')>Sí</option>
                                            <option value="N" @selected(old($indicator['name']) === 'N')>No</option>
                                        </select>
                                        @if($errors->has($indicator['name']))
                                            <p class="mt-2 text-sm font-medium text-red-700">{{ $errors->first($indicator['name']) }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <p class="rounded-xl bg-indigo-50 px-4 py-3 text-xs leading-6 text-indigo-900">
                                No hay respuestas precargadas: seleccioná cada una según la documentación real.
                                El sistema rechazará valores contradictorios entre líneas.
                            </p>
                        </div>
                    </section>

                    <section aria-label="Aviso de guardado" class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                        <p class="font-bold">Guardar línea no significa transmitir a Aduana.</p>
                        <p class="mt-1">
                            La línea se guarda como borrador y conserva el peso total del conocimiento.
                            Después habrá que comprobar todas las líneas y, cuando corresponda, vincular los contenedores reales antes de transmitir.
                        </p>
                    </section>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ $operationUrl }}"
                           class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Cancelar y volver
                        </a>
                        <button type="submit" @disabled($cargoTypes->isEmpty())
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:bg-gray-400">
                            Guardar línea como borrador
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                            </svg>
                        </button>
                    </div>
                </form>

                <aside aria-label="Ayuda para cargar la línea" class="space-y-5 lg:col-span-4">
                    <section class="rounded-lg bg-gray-100 p-5 text-gray-800 shadow-lg sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-700">Guía de la operación</p>
                        <h2 class="mt-2 text-xl font-bold">Antes de guardar</h2>
                        <ol class="mt-5 space-y-4 text-sm text-gray-200">
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-500 font-bold">1</span>
                                <span>Verificá el número, la descripción y el peso de esta línea.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-500 font-bold">2</span>
                                <span>Confirmá embalaje, condición H/P y posición arancelaria, sin suponer valores.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-500 font-bold">3</span>
                                <span>Elegí expresamente Sí o No para cada indicador aduanero.</span>
                            </li>
                        </ol>
                        <p class="mt-6 border-t border-gray-200 pt-5 text-xs leading-6 text-gray-600">
                            Al guardar volverás al expediente. Esta línea seguirá siendo un borrador.
                        </p>
                    </section>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-600">
                        <p class="font-bold text-gray-900">¿Falta algún dato?</p>
                        <p class="mt-2 leading-6">
                            Consultá el documento o el catálogo correspondiente. No ingreses un código parecido para poder continuar: la información debe coincidir con lo declarado.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
