{{-- ATA-DESC: operaciones independientes del viaje de la transportista. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <nav aria-label="Ubicación" class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                <a href="{{ route('company.dashboard') }}" class="hover:text-indigo-700 focus-visible:underline">Inicio</a>
                <span aria-hidden="true">/</span>
                <span>Aduana Argentina</span>
                <span aria-hidden="true">/</span>
                <span class="text-slate-800">Desconsolidados</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Mis desconsolidaciones</h1>
            <p class="mt-1 text-sm text-slate-500">Operaciones de {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto max-w-7xl space-y-7 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">

            @if(session('success'))
                <div role="status" class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>
                    </svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            @if($errors->any())
                <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Revisá estos datos:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Punto de entrada único: no se solicita barco ni viaje local. --}}
            <section aria-labelledby="start-heading" class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-white shadow-xl">
                <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-28 h-72 w-72 rounded-full border border-white/10"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-36 right-20 h-80 w-80 rounded-full bg-indigo-500/10 blur-3xl"></div>
                <div class="relative grid gap-8 p-5 sm:p-8 lg:grid-cols-5 lg:items-center lg:gap-12 lg:p-10">
                    <div class="lg:col-span-3">
                        <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold tracking-wide text-indigo-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8m-8 5h8m-8 5h5M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/>
                            </svg>
                            ARGENTINA · ATA-DESC
                        </div>
                        <h2 id="start-heading" class="max-w-xl text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
                            Una operación nueva empieza con tres datos.
                        </h2>
                        <p class="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">
                            Usá las referencias que te informó Aduana para identificar el título madre.
                            Después podrás agregar los conocimientos hijos. No necesitás cargar el barco ni el viaje de la transportista.
                        </p>
                        <a href="{{ route('company.simple.desconsolidado.operations.create') }}"
                           class="mt-7 inline-flex min-h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-white px-6 py-3 text-sm font-bold text-slate-900 shadow-lg shadow-black/10 transition hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white sm:w-auto">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                            </svg>
                            Iniciar desconsolidación
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                            </svg>
                        </a>
                        <p class="mt-3 text-xs text-slate-400">Si ya empezaste una operación, podés retomarla desde la lista de abajo.</p>
                    </div>

                    <div class="rounded-2xl border border-white/15 bg-white/10 p-5 shadow-inner shadow-black/10 backdrop-blur-sm sm:p-6 lg:col-span-2">
                        <h3 class="text-base font-bold text-white">¿Qué necesitás para empezar?</h3>
                        <p class="mt-1 text-sm text-slate-300">Solo las referencias del título madre.</p>
                        <ol class="mt-5 space-y-4">
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-400/20 text-sm font-bold text-indigo-100">1</span>
                                <div>
                                    <p class="text-sm font-semibold">Identificador del viaje AFIP</p>
                                    <p class="mt-0.5 text-xs leading-5 text-slate-300">El número que asignó Aduana al viaje.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-400/20 text-sm font-bold text-indigo-100">2</span>
                                <div>
                                    <p class="text-sm font-semibold">Puerto de embarque del título madre</p>
                                    <p class="mt-0.5 text-xs leading-5 text-slate-300">Su código de 5 caracteres.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-400/20 text-sm font-bold text-indigo-100">3</span>
                                <div>
                                    <p class="text-sm font-semibold">Número del conocimiento madre</p>
                                    <p class="mt-0.5 text-xs leading-5 text-slate-300">Tal como figura en la documentación.</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <section aria-labelledby="saved-heading" class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="saved-heading" class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Operaciones guardadas</h2>
                        <p class="mt-1 text-sm text-slate-500">Retomá una desconsolidación desde donde la dejaste.</p>
                    </div>
                    <p class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600">
                        <span class="font-bold text-slate-900">{{ $operations->total() }}</span>
                        {{ $operations->total() === 1 ? 'operación' : 'operaciones' }}
                    </p>
                </div>

                @forelse($operations as $operation)
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-indigo-200 hover:shadow-md">
                        <div class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 4.5h8l4 4V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2h2Z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 4.5V9h6M8 13h8M8 17h5"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Conocimiento madre</p>
                                        <h3 class="mt-1 break-words text-lg font-bold text-slate-900" title="{{ $operation->master_bill_number }}">{{ $operation->master_bill_number }}</h3>
                                        <p class="mt-1 text-xs text-slate-500">Iniciada el {{ $operation->created_at?->format('d/m/Y') ?? '—' }}</p>
                                    </div>
                                </div>
                                <dl class="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-3">
                                    <div class="min-w-0">
                                        <dt class="text-xs font-medium text-slate-500">Viaje AFIP</dt>
                                        <dd class="mt-1 break-all text-sm font-semibold text-slate-900">{{ $operation->argentina_voyage_id }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-xs font-medium text-slate-500">Puerto del título madre</dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $operation->master_loading_port_code }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-xs font-medium text-slate-500">Conocimientos hijos cargados</dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $operation->bills_of_lading_count }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 lg:w-48 lg:shrink-0 lg:border-l lg:border-t-0 lg:py-2 lg:pl-5">
                                <p class="text-xs leading-5 text-slate-500">
                                    {{ $operation->bills_of_lading_count > 0
                                        ? 'Podés revisar los conocimientos que guardaste.'
                                        : 'Todavía no se cargaron conocimientos hijos.' }}
                                </p>
                                <a href="{{ route('company.simple.desconsolidado.operations.show', ['operation' => $operation->id]) }}"
                                   class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    Continuar
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm sm:py-16">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l4 4v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 4v5h6M12 12v6m-3-3h6"/>
                            </svg>
                        </div>
                        <h3 class="mt-5 text-xl font-bold text-slate-900">Todavía no hay desconsolidaciones</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                            Empezá la primera con los datos del título madre que recibiste de Aduana. Se guardará para que puedas continuar más tarde.
                        </p>
                        <a href="{{ route('company.simple.desconsolidado.operations.create') }}"
                           class="mt-6 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                            </svg>
                            Crear mi primera operación
                        </a>
                    </div>
                @endforelse

                @if($operations->hasPages())
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        {{ $operations->links() }}
                    </div>
                @endif
            </section>

            <div class="flex items-start gap-2 text-xs leading-5 text-slate-500">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.5c0 4.5 3 7.8 7.5 9.5 4.5-1.7 7.5-5 7.5-9.5V6L12 3Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>
                </svg>
                Solo se muestran operaciones de tu empresa. La creación de un expediente no realiza ningún envío a Aduana.
            </div>
        </div>
    </div>
</x-app-layout>
