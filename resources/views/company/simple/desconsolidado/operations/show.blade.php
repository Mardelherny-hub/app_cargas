{{-- ATA-DESC: expediente propio de la desconsolidadora, sin viaje de transporte local. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <nav aria-label="Ubicación" class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                <a href="{{ route('company.dashboard') }}" class="hover:text-indigo-700 focus-visible:underline">Inicio</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
                   class="hover:text-indigo-700 focus-visible:underline">Mis desconsolidaciones</a>
                <span aria-hidden="true">/</span>
                <span class="text-slate-800">Detalle de la operación</span>
            </nav>
            <h1 class="break-words text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                Desconsolidación · {{ $operation->master_bill_number }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">Empresa: {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto max-w-7xl space-y-7 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
            <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
               class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-slate-600 hover:text-indigo-700 focus-visible:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                </svg>
                Volver a mis desconsolidaciones
            </a>

            @if(session('success'))
                <div role="status" class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>
                    </svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Revisá estos datos:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Resumen verificable: referencias guardadas; no equivale a registro en Aduana. --}}
            <section aria-labelledby="summary-heading" class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-5 text-white shadow-xl sm:p-8 lg:p-9">
                <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full border border-white/10"></div>
                <div class="relative">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-indigo-100">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8m-8 5h8m-8 5h5M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/>
                                </svg>
                                EXPEDIENTE ATA-DESC
                            </div>
                            <h2 id="summary-heading" class="text-2xl font-bold tracking-tight sm:text-3xl">Referencias guardadas</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                                Esta operación está identificada en la aplicación. Todavía no significa que los conocimientos hijos hayan sido registrados ante Aduana.
                            </p>
                        </div>
                        <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-xl bg-emerald-400/15 px-3 py-2 text-xs font-semibold text-emerald-100">
                            <span class="h-2 w-2 rounded-full bg-emerald-300" aria-hidden="true"></span>
                            Referencias guardadas
                        </span>
                    </div>
                    <dl class="mt-7 grid gap-3 sm:grid-cols-3">
                        <div class="min-w-0 rounded-2xl border border-white/15 bg-white/10 p-4">
                            <dt class="text-xs font-medium text-slate-300">Identificador del viaje AFIP</dt>
                            <dd class="mt-2 break-all text-base font-bold text-white">{{ $operation->argentina_voyage_id }}</dd>
                        </div>
                        <div class="min-w-0 rounded-2xl border border-white/15 bg-white/10 p-4">
                            <dt class="text-xs font-medium text-slate-300">Puerto de embarque del título madre</dt>
                            <dd class="mt-2 text-base font-bold text-white">{{ $operation->master_loading_port_code }}</dd>
                        </div>
                        <div class="min-w-0 rounded-2xl border border-white/15 bg-white/10 p-4">
                            <dt class="text-xs font-medium text-slate-300">Número del conocimiento madre</dt>
                            <dd class="mt-2 break-words text-base font-bold text-white">{{ $operation->master_bill_number }}</dd>
                        </div>
                    </dl>
                    <p class="mt-5 text-xs text-slate-300">
                        Expediente iniciado el {{ $operation->created_at?->format('d/m/Y') ?? '—' }}. No se solicitó información de la embarcación.
                    </p>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <section aria-labelledby="children-heading" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:col-span-8">
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7 sm:py-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Paso 2 · Documentación</p>
                            <h2 id="children-heading" class="mt-1 text-xl font-bold text-slate-900">Conocimientos hijos</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Los conocimientos asociados a este expediente.</p>
                        </div>
                        <div class="flex flex-col gap-3 sm:items-end">
                            <span class="inline-flex w-fit items-center gap-2 rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-800">
                                {{ $operation->bills_of_lading_count }}
                                {{ (int) $operation->bills_of_lading_count === 1 ? 'conocimiento' : 'conocimientos' }}
                            </span>
                            <a href="{{ route('company.simple.desconsolidado.operations.bills.create', ['operation' => $operation->id]) }}"
                               class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                                </svg>
                                Agregar conocimiento hijo
                            </a>
                        </div>
                    </div>

                    @forelse($operation->billsOfLading as $bill)
                        <article class="border-b border-slate-100 px-5 py-5 last:border-b-0 sm:px-7">
                            <div class="flex items-start gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l4 4v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 4v5h6M8 13h8M8 17h5"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-slate-500">Número de conocimiento hijo</p>
                                    <h3 class="mt-1 break-words text-lg font-bold text-slate-900">{{ $bill->bill_number }}</h3>
                                    <p class="mt-2 text-xs leading-5 text-slate-500">
                                        Guardado en esta operación. La presentación ante Aduana debe verificarse por separado.
                                    </p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-12 text-center sm:px-8 sm:py-16">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l4 4v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 4v5h6M12 12v6m-3-3h6"/>
                                </svg>
                            </div>
                            <h3 class="mt-5 text-lg font-bold text-slate-900">Todavía no hay conocimientos hijos</h3>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">
                                Las referencias del título madre ya están guardadas. Ahora podés agregar el primer conocimiento hijo y conservarlo como borrador.
                            </p>
                            <a href="{{ route('company.simple.desconsolidado.operations.bills.create', ['operation' => $operation->id]) }}"
                               class="mt-6 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                                </svg>
                                Agregar primer conocimiento hijo
                            </a>
                        </div>
                    @endforelse

                    <div class="border-t border-amber-200 bg-amber-50 px-5 py-4 sm:px-7">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.8 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-bold text-amber-900">Los conocimientos se guardan como borradores</p>
                                <p class="mt-1 text-sm leading-6 text-amber-800">
                                    Ya podés agregar conocimientos hijos a este expediente.
                                    La edición, las líneas de mercadería y los contenedores se incorporarán en las próximas etapas.
                                    Todavía no está habilitado el envío a Aduana desde este expediente.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <aside aria-labelledby="steps-heading" class="space-y-5 lg:col-span-4">
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Guía de la operación</p>
                        <h2 id="steps-heading" class="mt-1 text-xl font-bold text-slate-900">¿Qué sigue ahora?</h2>
                        <ol class="mt-6 space-y-5">
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-bold text-slate-900">1. Identificar el título madre</p>
                                    <p class="mt-1 text-xs leading-5 text-emerald-700">Referencias guardadas.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-sm font-bold text-indigo-700">2</span>
                                <div>
                                    <p class="text-sm font-bold text-slate-900">2. Cargar conocimientos hijos</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">
                                        {{ $operation->bills_of_lading_count > 0
                                            ? 'Ya hay conocimientos guardados. Podés agregar otros. La edición sigue pendiente.'
                                            : 'Podés agregar ahora el primer conocimiento hijo. Quedará guardado como borrador.' }}
                                    </p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-bold text-slate-500">3</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">3. Revisar los datos</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">Controlar los conocimientos y completar los campos requeridos.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-bold text-slate-500">4</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">4. Transmitir a Aduana</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">El envío no está habilitado desde este expediente.</p>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg sm:p-6">
                        <p class="text-sm font-bold">Tu expediente está guardado</p>
                        <p class="mt-2 text-sm leading-6 text-slate-300">
                            Podés salir y volver más tarde. Las referencias y los conocimientos asociados permanecerán en tu expediente.
                        </p>
                        <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
                           class="mt-5 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-900 transition hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                            Volver a mis desconsolidaciones
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                            </svg>
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
