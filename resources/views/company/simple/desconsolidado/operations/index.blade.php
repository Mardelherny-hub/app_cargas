{{-- ATA-DESC: operaciones independientes del viaje de la transportista. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <nav aria-label="Ubicación" class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium text-gray-500">
                <a href="{{ route('company.dashboard') }}" class="hover:text-indigo-700 focus-visible:underline">Inicio</a>
                <span aria-hidden="true">/</span>
                <span>Aduana Argentina</span>
                <span aria-hidden="true">/</span>
                <span class="text-gray-800">Desconsolidados</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">Mis desconsolidaciones</h1>
            <p class="mt-1 text-sm text-gray-500">Operaciones de {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-50">
        <div class="mx-auto max-w-7xl space-y-7 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">

            @if(session('success'))
                <div role="status" class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>
                    </svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            @if($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Revisá estos datos:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Acceso independiente a ATA-DESC, usando el diseño habitual de la aplicación. --}}
            <section aria-labelledby="start-heading" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 id="start-heading" class="text-xl font-semibold text-gray-800">Nueva desconsolidación</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Para comenzar, ingresá el identificador del viaje AFIP, el código del puerto de embarque del conocimiento madre
                    y el número de ese conocimiento.
                </p>
                <a href="{{ route('company.simple.desconsolidado.operations.create') }}"
                   class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Iniciar desconsolidación
                </a>
                <p class="mt-3 text-sm text-gray-500">Si ya empezaste una operación, podés retomarla desde la lista de abajo.</p>
            </section>

            <section aria-labelledby="saved-heading" class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="saved-heading" class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">Operaciones guardadas</h2>
                        <p class="mt-1 text-sm text-gray-500">Retomá una desconsolidación desde donde la dejaste.</p>
                    </div>
                    <p class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-600">
                        <span class="font-bold text-gray-900">{{ $operations->total() }}</span>
                        {{ $operations->total() === 1 ? 'operación' : 'operaciones' }}
                    </p>
                </div>

                @forelse($operations as $operation)
                    <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-indigo-200 hover:shadow-md">
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
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Conocimiento madre</p>
                                        <h3 class="mt-1 break-words text-lg font-bold text-gray-900" title="{{ $operation->master_bill_number }}">{{ $operation->master_bill_number }}</h3>
                                        <p class="mt-1 text-xs text-gray-500">Iniciada el {{ $operation->created_at?->format('d/m/Y') ?? '—' }}</p>
                                    </div>
                                </div>
                                <dl class="mt-5 grid gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-3">
                                    <div class="min-w-0">
                                        <dt class="text-xs font-medium text-gray-500">Viaje AFIP</dt>
                                        <dd class="mt-1 break-all text-sm font-semibold text-gray-900">{{ $operation->argentina_voyage_id }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-xs font-medium text-gray-500">Puerto de embarque del título madre</dt>
                                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $operation->master_loading_port_code }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-xs font-medium text-gray-500">Conocimientos hijos cargados</dt>
                                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $operation->bills_of_lading_count }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 lg:w-48 lg:shrink-0 lg:border-l lg:border-t-0 lg:py-2 lg:pl-5">
                                <p class="text-xs leading-5 text-gray-500">
                                    {{ $operation->bills_of_lading_count > 0
                                        ? 'Podés revisar los conocimientos que guardaste.'
                                        : 'Podés agregar el primer conocimiento hijo desde este expediente.' }}
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
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center shadow-sm sm:py-16">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l4 4v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 4v5h6M12 12v6m-3-3h6"/>
                            </svg>
                        </div>
                        <h3 class="mt-5 text-xl font-bold text-gray-900">Todavía no hay desconsolidaciones</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-500">
                            Iniciá un expediente con las referencias del conocimiento madre. Podrás retomarlo para agregar los conocimientos hijos.
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
                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                        {{ $operations->links() }}
                    </div>
                @endif
            </section>

            <div class="flex items-start gap-2 text-xs leading-5 text-gray-500">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.5c0 4.5 3 7.8 7.5 9.5 4.5-1.7 7.5-5 7.5-9.5V6L12 3Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>
                </svg>
                Esta lista contiene las operaciones de tu empresa. Crear o guardar un expediente no transmite información a Aduana.
            </div>
        </div>
    </div>
</x-app-layout>
