{{-- ATA-DESC: asociar un contenedor existente, sin crear ni modificar su ficha global. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <nav aria-label="Ubicación" class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                <a href="{{ route('company.dashboard') }}" class="hover:text-indigo-700 focus-visible:underline">Inicio</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.index') }}" class="hover:text-indigo-700 focus-visible:underline">Desconsolidados</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]) }}" class="hover:text-indigo-700 focus-visible:underline">Expediente</a>
                <span aria-hidden="true">/</span>
                <span class="text-slate-800">Vincular contenedor</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Vincular contenedor</h1>
            <p class="mt-1 text-sm text-slate-500">Empresa: {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    @php
        $operationUrl = route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]);
    @endphp

    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
            <a href="{{ $operationUrl }}"
               class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-slate-600 hover:text-indigo-700 focus-visible:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                </svg>
                Volver al expediente
            </a>

            <section aria-label="Conocimiento y línea de mercadería"
                     class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-5 text-white shadow-lg sm:p-7">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-300">ATA-DESC · Contenedores</p>
                        <h2 class="mt-2 break-words text-2xl font-bold">Conocimiento {{ $bill->bill_number }}</h2>
                        <p class="mt-2 break-words text-sm text-slate-300">Título madre: {{ $operation->master_bill_number }}</p>
                        <p class="mt-1 text-xs text-slate-400">Identificador de viaje AFIP: {{ $operation->argentina_voyage_id }}</p>
                    </div>
                    <div class="shrink-0 rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-xs font-medium text-slate-300">Línea de mercadería</p>
                        <p class="mt-1 text-xl font-bold">N.º {{ $item->line_number }}</p>
                        <p class="mt-1 text-xs text-slate-200">Embalaje {{ $item->packaging_code }} · Condición {{ $item->container_condition }}</p>
                    </div>
                </div>
                <p class="mt-5 border-t border-white/15 pt-4 text-sm leading-6 text-slate-200">
                    {{ $item->item_description }}
                </p>
            </section>

            @if($errors->any())
                <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-900">
                    <p class="font-bold">No se pudo guardar la vinculación</p>
                    <p class="mt-1">Revisá el número informado. El texto que ingresaste se mantiene.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <div class="space-y-6 lg:col-span-8">
                    <section aria-labelledby="link-heading" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-5 sm:px-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">01 · Identificar el contenedor</p>
                            <h2 id="link-heading" class="mt-1 text-xl font-bold text-slate-900">Ingresá el número real</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">
                                La aplicación buscará el contenedor exacto entre los que ya están registrados y habilitados.
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
                                <label for="container_number" class="block text-sm font-bold text-slate-900">
                                    Número de contenedor <span class="text-red-600">*</span>
                                </label>
                                <p id="container-number-help" class="mt-1 text-sm leading-6 text-slate-500">
                                    Copialo de la documentación. Debe coincidir con un contenedor activo y no bloqueado del sistema.
                                </p>
                                <input id="container_number" name="container_number" type="text"
                                       value="{{ old('container_number') }}" maxlength="15" required
                                       autocomplete="off" spellcheck="false" autocapitalize="characters"
                                       aria-describedby="container-number-help{{ $errors->has('container_number') ? ' container-number-error' : '' }}"
                                       aria-invalid="{{ $errors->has('container_number') ? 'true' : 'false' }}"
                                       placeholder="Número completo del contenedor"
                                       class="mt-3 block min-h-12 w-full rounded-xl border px-4 py-3 font-mono text-base uppercase text-slate-900 shadow-sm placeholder:font-sans placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 {{ $errors->has('container_number') ? 'border-red-400 bg-red-50/30' : 'border-slate-300 bg-white' }}"/>
                                @error('container_number')
                                    <p id="container-number-error" class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-xs leading-5 text-slate-500">
                                    Hasta 15 caracteres. Se eliminan los espacios iniciales y finales, y se convierte a mayúsculas antes de buscar.
                                    No se cargan todos los contenedores del catálogo.
                                </p>
                            </div>

                            <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm leading-6 text-indigo-900">
                                <p class="font-bold">Solo se crea la asociación con esta línea.</p>
                                <p class="mt-1">
                                    Si el contenedor ya está vinculado, no se duplicará.
                                    Si no existe en el catálogo, no se dará de alta automáticamente.
                                </p>
                            </div>
                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                                <a href="{{ $operationUrl }}"
                                   class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
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
                    </section>

                    <section aria-labelledby="linked-heading" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-7">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">02 · Asociaciones guardadas</p>
                                <h2 id="linked-heading" class="mt-1 text-xl font-bold text-slate-900">Contenedores de esta línea</h2>
                            </div>
                            <span class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">
                                {{ $linkedContainers->count() }}
                            </span>
                        </div>
                        @if($linkedContainers->isEmpty())
                            <div class="p-6 sm:p-7">
                                <p class="text-sm font-semibold text-slate-900">Todavía no hay contenedores vinculados.</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    Ingresá arriba el número real del primero. No se generarán unidades ni cantidades ficticias.
                                </p>
                            </div>
                        @else
                            <ul class="divide-y divide-slate-100" aria-label="Contenedores vinculados a la línea">
                                @foreach($linkedContainers as $container)
                                    <li class="flex items-center gap-3 px-5 py-4 sm:px-7">
                                        <svg class="h-5 w-5 shrink-0 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h15v9H3zM18 11h3v6h-3M6 20h3m6 0h3M6 11h6"/>
                                        </svg>
                                        <span class="min-w-0 break-all font-mono text-sm font-bold text-slate-900">{{ $container->container_number }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>

                <aside aria-label="Información importante" class="space-y-5 lg:col-span-4">
                    <section class="rounded-3xl bg-slate-900 p-5 text-white shadow-lg sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-300">Antes de continuar</p>
                        <h2 class="mt-2 text-xl font-bold">¿Qué conserva el sistema?</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-200">
                            El estado físico, los precintos y los datos generales del contenedor no cambian.
                            La condición H/P de esta asociación proviene de la línea de mercadería.
                        </p>
                        <p class="mt-3 text-sm leading-6 text-slate-200">
                            Tampoco se inventan pesos, cantidades de bultos ni distribución física por contenedor.
                        </p>
                    </section>
                    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                        <p class="font-bold">Vincular no significa transmitir a Aduana.</p>
                        <p class="mt-2">
                            La documentación CSC o ACEP, y el resto de las validaciones ATA-DESC,
                            deberán verificarse antes del envío. Este formulario no permite modificarlos.
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
