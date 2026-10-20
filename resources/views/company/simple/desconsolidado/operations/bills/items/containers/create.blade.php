{{-- ATA-DESC: asociar un contenedor existente, sin crear ni modificar su ficha global. --}}
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
                <span class="text-gray-800">Vincular contenedor</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">Vincular contenedor</h1>
            <p class="mt-1 text-sm text-gray-500">Empresa: {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    @php
        $operationUrl = route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]);
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

            <section aria-label="Conocimiento y línea de mercadería"
                     class="rounded-lg bg-gray-100 p-5 text-gray-800 shadow-lg sm:p-7">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-700">ATA-DESC · Contenedores</p>
                        <h2 class="mt-2 break-words text-2xl font-bold">Conocimiento {{ $bill->bill_number }}</h2>
                        <p class="mt-2 break-words text-sm text-gray-600">Título madre: {{ $operation->master_bill_number }}</p>
                        <p class="mt-1 text-xs text-gray-600">Identificador de viaje AFIP: {{ $operation->argentina_voyage_id }}</p>
                    </div>
                    <div class="shrink-0 rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-xs font-medium text-gray-600">Línea de mercadería</p>
                        <p class="mt-1 text-xl font-bold">N.º {{ $item->line_number }}</p>
                        <p class="mt-1 text-xs text-gray-700">Embalaje {{ $item->packaging_code }} · Condición {{ $item->container_condition }}</p>
                    </div>
                </div>
                <p class="mt-5 border-t border-gray-200 pt-4 text-sm leading-6 text-gray-700">
                    {{ $item->item_description }}
                </p>
            </section>

            @if($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-900">
                    <p class="font-bold">No se pudo guardar la vinculación</p>
                    <p class="mt-1">Revisá las observaciones indicadas. El número ingresado se conserva.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <div class="space-y-6 lg:col-span-8">
                    <section aria-labelledby="link-heading" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">01 · Identificar el contenedor</p>
                            <h2 id="link-heading" class="mt-1 text-xl font-bold text-gray-900">Identificá el contenedor</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-500">
                                El sistema buscará el número indicado entre los contenedores registrados y habilitados.
                            </p>
                        </div>

                        <form method="POST"
                              action="{{ route('company.simple.desconsolidado.operations.bills.items.containers.store', [
                                  'operation' => $operation->id,
                                  'bill' => $bill->id,
                                  'item' => $item->id,
                              ]) }}"
                              class="space-y-5 p-5 sm:p-7">
                            @csrf
                            <div>
                                <label for="container_number" class="block text-sm font-bold text-gray-900">
                                    Número de contenedor <span class="text-red-600">*</span>
                                </label>
                                <p id="container-number-help" class="mt-1 text-sm leading-6 text-gray-500">
                                    Ingresá el número completo que figura en la documentación. El contenedor debe estar registrado, activo y no bloqueado.
                                </p>
                                <input id="container_number" name="container_number" type="text"
                                       value="{{ old('container_number') }}" maxlength="15" required
                                       autocomplete="off" spellcheck="false" autocapitalize="characters"
                                       aria-describedby="container-number-help{{ $errors->has('container_number') ? ' container-number-error' : '' }}"
                                       aria-invalid="{{ $errors->has('container_number') ? 'true' : 'false' }}"
                                       placeholder="Número completo del contenedor"
                                       class="mt-3 block min-h-12 w-full rounded-xl border px-4 py-3 font-mono text-base uppercase text-gray-900 shadow-sm placeholder:font-sans placeholder:text-gray-600 focus:border-indigo-500 focus:ring-indigo-500 {{ $errors->has('container_number') ? 'border-red-400 bg-red-50/30' : 'border-gray-300 bg-white' }}"/>
                                @error('container_number')
                                    <p id="container-number-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-xs leading-5 text-gray-500">
                                    Se admiten hasta 15 caracteres. El sistema omite los espacios iniciales y finales y convierte las letras a mayúsculas.
                                </p>
                            </div>

                            <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm leading-6 text-indigo-900">
                                <p class="font-bold">Este paso vincula el contenedor con esta línea de mercadería.</p>
                                <p class="mt-1">
                                    Si el contenedor ya está vinculado, se conserva la asociación existente.
                                    Si todavía no está registrado, podés utilizar la opción «Registrar contenedor nuevo».
                                </p>
                            </div>
                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                                <a href="{{ $operationUrl }}"
                                   class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-100">
                                    Cancelar
                                </a>
                                <button type="submit"
                                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    Vincular contenedor existente
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                                    </svg>
                                </button>
                            </div>
                        </form>

                        <div class="border-t border-gray-100 bg-gray-50 px-5 py-5 sm:px-7">
                            <p class="text-sm font-bold text-gray-900">¿El contenedor todavía no existe en el catálogo?</p>
                            <p class="mt-1 text-sm leading-6 text-gray-600">
                                Si el contenedor todavía no está registrado, podés darlo de alta con su número y los estados físico y operativo correspondientes.
                                El nuevo registro quedará disponible en el catálogo general.
                            </p>
                            <a href="{{ route('company.simple.desconsolidado.operations.bills.items.containers.register', [
                                    'operation' => $operation->id,
                                    'bill' => $bill->id,
                                    'item' => $item->id,
                                ]) }}"
                               class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-white px-4 py-2.5 text-sm font-bold text-indigo-700 transition hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                                </svg>
                                Registrar contenedor nuevo
                            </a>
                        </div>
                    </section>

                    <section aria-labelledby="linked-heading" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-5 sm:px-7">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">02 · Asociaciones guardadas</p>
                                <h2 id="linked-heading" class="mt-1 text-xl font-bold text-gray-900">Contenedores de esta línea</h2>
                            </div>
                            <span class="rounded-xl bg-gray-100 px-3 py-2 text-sm font-bold text-gray-700">
                                {{ $linkedContainers->count() }}
                            </span>
                        </div>
                        @if($linkedContainers->isEmpty())
                            <div class="p-6 sm:p-7">
                                <p class="text-sm font-semibold text-gray-900">Todavía no hay contenedores vinculados.</p>
                                <p class="mt-2 text-sm leading-6 text-gray-600">
                                    Ingresá el número de un contenedor ya registrado para vincularlo a esta línea.
                                </p>
                            </div>
                        @else
                            <ul class="divide-y divide-slate-100" aria-label="Contenedores vinculados a la línea">
                                @foreach($linkedContainers as $container)
                                    <li class="flex items-center gap-3 px-5 py-4 sm:px-7">
                                        <svg class="h-5 w-5 shrink-0 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h15v9H3zM18 11h3v6h-3M6 20h3m6 0h3M6 11h6"/>
                                        </svg>
                                        <span class="min-w-0 break-all font-mono text-sm font-bold text-gray-900">{{ $container->container_number }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>

                <aside aria-label="Información importante" class="space-y-5 lg:col-span-4">
                    <section class="rounded-lg bg-gray-100 p-5 text-gray-800 shadow-lg sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-700">Antes de continuar</p>
                        <h2 class="mt-2 text-xl font-bold">¿Qué datos se mantienen?</h2>
                        <p class="mt-4 text-sm leading-6 text-gray-700">
                            El estado físico, los precintos y los datos generales del contenedor no cambian.
                            La condición H/P de esta asociación proviene de la línea de mercadería.
                        </p>
                        <p class="mt-3 text-sm leading-6 text-gray-700">
                            La vinculación no modifica los pesos ni las cantidades de bultos, ni asigna su distribución entre contenedores.
                        </p>
                    </section>
                    <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                        <p class="font-bold">Esta vinculación no realiza envíos a Aduana.</p>
                        <p class="mt-2">
                            Antes de transmitir, se deben revisar los datos documentales CSC o ACEP y las demás validaciones ATA-DESC.
                            Esta pantalla únicamente permite vincular el contenedor a la línea.
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
