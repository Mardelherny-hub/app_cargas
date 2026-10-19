{{-- ATA-DESC: expediente propio de la desconsolidadora, sin viaje de transporte local. --}}
<x-app-layout>
    <x-slot name="header">
        <div>
            <nav aria-label="Ubicación" class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium text-gray-500">
                <a href="{{ route('company.dashboard') }}" class="hover:text-indigo-700 focus-visible:underline">Inicio</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
                   class="hover:text-indigo-700 focus-visible:underline">Mis desconsolidaciones</a>
                <span aria-hidden="true">/</span>
                <span class="text-gray-800">Detalle de la operación</span>
            </nav>
            <h1 class="break-words text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">
                Desconsolidación · {{ $operation->master_bill_number }}
            </h1>
            <p class="mt-1 text-sm text-gray-500">Empresa: {{ $company->legal_name }}</p>
        </div>
    </x-slot>

    <div class="min-h-screen bg-gray-50">
        <div class="mx-auto max-w-7xl space-y-7 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
            <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
               class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-gray-600 hover:text-indigo-700 focus-visible:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                </svg>
                Volver a mis desconsolidaciones
            </a>

            @if(session('success'))
                <div role="status" class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>
                    </svg>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Revisá estos datos:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Resumen verificable: referencias guardadas; no equivale a registro en Aduana. --}}
            <section aria-labelledby="summary-heading" class="relative isolate overflow-hidden rounded-lg bg-gray-100 p-5 text-gray-800 shadow-xl sm:p-8 lg:p-9">
                <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full border border-white/10"></div>
                <div class="relative">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8m-8 5h8m-8 5h5M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/>
                                </svg>
                                EXPEDIENTE ATA-DESC
                            </div>
                            <h2 id="summary-heading" class="text-2xl font-bold tracking-tight sm:text-3xl">Referencias guardadas</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">
                                Esta operación está identificada en la aplicación. Todavía no significa que los conocimientos hijos hayan sido registrados ante Aduana.
                            </p>
                        </div>
                        <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-xl bg-green-100 px-3 py-2 text-xs font-semibold text-green-700">
                            <span class="h-2 w-2 rounded-full bg-emerald-300" aria-hidden="true"></span>
                            Referencias guardadas
                        </span>
                    </div>
                    <dl class="mt-7 grid gap-3 sm:grid-cols-3">
                        <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4">
                            <dt class="text-xs font-medium text-gray-600">Identificador del viaje AFIP</dt>
                            <dd class="mt-2 break-all text-base font-bold text-gray-900">{{ $operation->argentina_voyage_id }}</dd>
                        </div>
                        <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4">
                            <dt class="text-xs font-medium text-gray-600">Puerto de embarque del título madre</dt>
                            <dd class="mt-2 text-base font-bold text-gray-900">{{ $operation->master_loading_port_code }}</dd>
                        </div>
                        <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4">
                            <dt class="text-xs font-medium text-gray-600">Número del conocimiento madre</dt>
                            <dd class="mt-2 break-words text-base font-bold text-gray-900">{{ $operation->master_bill_number }}</dd>
                        </div>
                    </dl>
                    <p class="mt-5 text-xs text-gray-600">
                        Expediente iniciado el {{ $operation->created_at?->format('d/m/Y') ?? '—' }}. No se solicitó información de la embarcación.
                    </p>
                </div>
            </section>

            @if((int) $operation->bills_of_lading_count === 0)
                <details class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <summary class="cursor-pointer text-sm font-bold text-indigo-700">Corregir referencias del expediente</summary>
                    <p class="mt-3 text-sm text-gray-600">Solo mientras no se hayan cargado conocimientos hijos.</p>
                    <form method="POST" action="{{ route('company.simple.desconsolidado.operations.update', ['operation' => $operation->id]) }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                        @csrf
                        @method('PUT')
                        <label class="text-sm font-semibold text-gray-800">Identificador del viaje AFIP
                            <input name="argentina_voyage_id" required maxlength="16" value="{{ old('argentina_voyage_id', $operation->argentina_voyage_id) }}" class="mt-2 w-full rounded-xl border-gray-300"/>
                        </label>
                        <label class="text-sm font-semibold text-gray-800">Puerto del título madre
                            <input name="master_loading_port_code" required maxlength="5" minlength="5" pattern="[A-Za-z0-9]{5}" value="{{ old('master_loading_port_code', $operation->master_loading_port_code) }}" class="mt-2 w-full rounded-xl border-gray-300 uppercase"/>
                        </label>
                        <label class="text-sm font-semibold text-gray-800">Conocimiento madre
                            <input name="master_bill_number" required maxlength="18" value="{{ old('master_bill_number', $operation->master_bill_number) }}" class="mt-2 w-full rounded-xl border-gray-300"/>
                        </label>
                        <button type="submit" class="min-h-11 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white hover:bg-indigo-700 sm:col-span-3 sm:justify-self-start">Guardar corrección</button>
                    </form>
                </details>
            @endif

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                <section aria-labelledby="children-heading" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm lg:col-span-8">
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7 sm:py-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Paso 2 · Documentación</p>
                            <h2 id="children-heading" class="mt-1 text-xl font-bold text-gray-900">Conocimientos hijos</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-500">Los conocimientos asociados a este expediente.</p>
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

                    @php
                        // Cargar una sola vez las líneas de TODOS los BL de este
                        // expediente y únicamente sus contenedores vinculados.
                        // Nunca se consulta el catálogo global de contenedores.
                        $operation->billsOfLading->loadMissing([
                            'shipmentItems' => fn ($query) => $query
                                ->orderBy('line_number')
                                ->with([
                                    'containers' => fn ($containers) => $containers
                                        ->orderBy('containers.container_number'),
                                ]),
                        ]);
                    @endphp

                    @forelse($operation->billsOfLading as $bill)
                        @php
                            // Reflejar las mismas condiciones de edición que exige
                            // DeconsolidationBillItemController::authorizedBill().
                            // La autorización definitiva sigue siendo del servidor.
                            $canAddLines = $bill->shipment_id === null
                                && $bill->status === 'draft'
                                && $bill->canBeEdited()
                                && $bill->is_house_bill
                                && (string) $bill->master_bill_number === (string) $operation->master_bill_number
                                && (string) data_get(
                                    $bill->webservice_data,
                                    'argentina.desconsolidated.master_loading_port_code'
                                ) === (string) $operation->master_loading_port_code
                                && !in_array($bill->argentina_status, ['sent', 'approved'], true)
                                && !in_array($bill->webservice_status, ['sent', 'processing', 'approved', 'accepted'], true);
                        @endphp
                        <article class="border-b border-gray-100 px-5 py-5 last:border-b-0 sm:px-7">
                            <div class="flex items-start gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-700">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l4 4v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 4v5h6M8 13h8M8 17h5"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-gray-500">Número de conocimiento hijo</p>
                                    <h3 class="mt-1 break-words text-lg font-bold text-gray-900">{{ $bill->bill_number }}</h3>
                                    <p class="mt-2 text-xs leading-5 text-gray-500">
                                        Guardado en esta operación. La presentación ante Aduana debe verificarse por separado.
                                    </p>
                                    @if($canAddLines)
                                        <a href="{{ route('company.simple.desconsolidado.operations.bills.edit', ['operation' => $operation->id, 'bill' => $bill->id]) }}" class="mt-3 inline-flex min-h-11 items-center rounded-xl border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700">Editar conocimiento hijo</a>
                                    @endif
                                    @if($canAddLines && $bill->shipmentItems->isEmpty())
                                        <form method="POST" action="{{ route('company.simple.desconsolidado.operations.bills.destroy', ['operation' => $operation->id, 'bill' => $bill->id]) }}" class="mt-3" onsubmit="return confirm('¿Archivar este conocimiento hijo sin líneas?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="min-h-11 rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Archivar conocimiento borrador</button>
                                        </form>
                                    @endif
                                    @if($canAddLines)
                                        <a href="{{ route('company.simple.desconsolidado.operations.bills.items.create', [
                                                'operation' => $operation->id,
                                                'bill' => $bill->id,
                                            ]) }}"
                                           class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-bold text-indigo-800 transition hover:border-indigo-300 hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                                            </svg>
                                            Agregar línea de mercadería
                                        </a>
                                    @else
                                        <p class="mt-3 text-xs font-medium text-gray-500">
                                            Este conocimiento no admite agregar líneas desde este expediente.
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <section aria-label="Líneas del conocimiento {{ $bill->bill_number }}"
                                     class="mt-5 border-t border-gray-100 pt-5">
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-bold text-gray-900">Líneas de mercadería</h4>
                                    <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700">
                                        {{ $bill->shipmentItems->count() }}
                                        {{ $bill->shipmentItems->count() === 1 ? 'línea' : 'líneas' }}
                                    </span>
                                </div>

                                @forelse($bill->shipmentItems as $line)
                                    @php
                                        $isContainerized = trim((string) $line->packaging_code) === '05';
                                        $lineCondition = strtoupper(trim((string) $line->container_condition));
                                        // Mismas condiciones del controlador de vinculación.
                                        $canLinkContainer = $canAddLines
                                            && $line->status === 'draft'
                                            && $isContainerized
                                            && in_array($lineCondition, ['H', 'P'], true);
                                    @endphp

                                    <div class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-4 last:mb-0 sm:p-5">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-sm font-bold text-gray-900">Línea {{ $line->line_number }}</p>
                                            <span class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600">
                                                Embalaje {{ $line->packaging_code ?: '—' }}
                                                · Condición {{ $lineCondition ?: '—' }}
                                            </span>
                                        </div>
                                        <p class="mt-2 break-words text-sm leading-6 text-gray-700">
                                            {{ $line->item_description ?: 'Sin descripción declarada' }}
                                        </p>
                                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <dt class="text-xs text-gray-500">Bultos declarados en la línea</dt>
                                                <dd class="mt-1 text-sm font-semibold text-gray-800">
                                                    {{ $line->package_quantity ?? '—' }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs text-gray-500">Peso bruto declarado de la línea</dt>
                                                <dd class="mt-1 text-sm font-semibold text-gray-800">
                                                    {{ $line->gross_weight_kg ?? '—' }} kg
                                                </dd>
                                            </div>
                                        </dl>

                                        @if($canAddLines && $line->status === 'draft')
                                            <a href="{{ route('company.simple.desconsolidado.operations.bills.items.edit', ['operation' => $operation->id, 'bill' => $bill->id, 'item' => $line->id]) }}" class="mt-3 inline-flex min-h-11 items-center rounded-xl border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700">Editar línea</a>
                                        @endif
                                        @if($canAddLines && $line->status === 'draft')
                                            <form method="POST" action="{{ route('company.simple.desconsolidado.operations.bills.items.destroy', ['operation' => $operation->id, 'bill' => $bill->id, 'item' => $line->id]) }}" class="mt-3" onsubmit="return confirm('¿Eliminar esta línea borrador y sus vínculos con contenedores? Las fichas físicas se conservarán.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="min-h-11 rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Eliminar línea borrador</button>
                                            </form>
                                        @endif

                                        @if($isContainerized)
                                            <div class="mt-4 border-t border-gray-200 pt-4">
                                                <p class="text-xs font-bold text-gray-700">
                                                    Contenedores vinculados: {{ $line->containers->count() }}
                                                </p>
                                                @if($line->containers->isNotEmpty())
                                                    <ul aria-label="Contenedores de la línea {{ $line->line_number }}"
                                                        class="mt-2 flex flex-wrap gap-2">
                                                        @foreach($line->containers as $container)
                                                            <li class="break-all rounded-lg border border-indigo-100 bg-white px-3 py-2 font-mono text-xs font-semibold text-gray-800">
                                                                {{ $container->container_number }}
                                                                @if($canLinkContainer)
                                                                    <form method="POST" action="{{ route('company.simple.desconsolidado.operations.bills.items.containers.destroy', ['operation' => $operation->id, 'bill' => $bill->id, 'item' => $line->id, 'container' => $container->id]) }}" class="mt-2" onsubmit="return confirm('¿Desvincular este contenedor sin borrar su ficha global?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="text-xs font-semibold text-red-700 underline">Desvincular</button>
                                                                    </form>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <p class="mt-2 text-xs leading-5 text-amber-800">
                                                        Todavía no hay contenedores vinculados a esta línea.
                                                    </p>
                                                @endif

                                                @if($canLinkContainer)
                                                    <a href="{{ route('company.simple.desconsolidado.operations.bills.items.containers.create', [
                                                            'operation' => $operation->id,
                                                            'bill' => $bill->id,
                                                            'item' => $line->id,
                                                        ]) }}"
                                                       class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-white px-4 py-2.5 text-sm font-bold text-indigo-700 transition hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                                                        </svg>
                                                        Vincular contenedor existente
                                                    </a>
                                                @elseif($canAddLines && $line->status === 'draft'
                                                    && !in_array($lineCondition, ['H', 'P'], true))
                                                    <p class="mt-3 text-xs font-medium leading-5 text-amber-800">
                                                        Falta una condición H/P válida antes de vincular contenedores.
                                                    </p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-5">
                                        <p class="text-sm font-semibold text-gray-800">Todavía no hay líneas de mercadería.</p>
                                        <p class="mt-1 text-xs leading-5 text-gray-500">
                                            Agregá las líneas declaradas en este conocimiento antes de vincular contenedores.
                                        </p>
                                    </div>
                                @endforelse
                            </section>
                        </article>
                    @empty
                        <div class="px-6 py-12 text-center sm:px-8 sm:py-16">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l4 4v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 4v5h6M12 12v6m-3-3h6"/>
                                </svg>
                            </div>
                            <h3 class="mt-5 text-lg font-bold text-gray-900">Todavía no hay conocimientos hijos</h3>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600">
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
                                @if($operation->billsOfLading->isNotEmpty())
                                    <form method="POST" action="{{ route('company.simple.desconsolidado.operations.transmit', ['operation' => $operation->id]) }}" class="mt-5 space-y-3 rounded-xl border border-amber-300 bg-white p-4">
                                        @csrf
                                        <p class="text-sm font-semibold text-gray-900">Operación ante Aduana Argentina</p>
                                        <label class="block text-sm font-medium text-gray-800" for="ata-desc-method">Acción</label>
                                        <select id="ata-desc-method" name="method" required class="w-full rounded-lg border-gray-300">
                                            <option value="">Elegí la operación</option>
                                            <option value="registrar">Registrar títulos</option>
                                            <option value="rectificar">Rectificar títulos registrados</option>
                                            <option value="eliminar">Eliminar títulos registrados en Aduana</option>
                                        </select>
                                        <fieldset class="space-y-2">
                                            <legend class="text-sm font-semibold text-gray-800">Conocimientos a procesar</legend>
                                            @foreach($operation->billsOfLading as $bill)
                                                <label class="flex items-start gap-2 text-sm text-gray-800">
                                                    <input type="checkbox" name="bill_ids[]" value="{{ $bill->id }}" class="mt-1 rounded border-gray-300"/>
                                                    <span>{{ $bill->bill_number }}</span>
                                                </label>
                                            @endforeach
                                        </fieldset>
                                        <label class="flex items-start gap-2 text-sm text-amber-900">
                                            <input type="checkbox" name="confirmation" value="1" required class="mt-1 rounded border-amber-400"/>
                                            <span>Confirmo que revisé la información. Esta operación puede modificar registros reales de Aduana.</span>
                                        </label>
                                        <button type="submit" class="min-h-11 rounded-xl bg-amber-700 px-4 py-2 text-sm font-bold text-white hover:bg-amber-800">Ejecutar operación aduanera</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('company.simple.desconsolidado.operations.preflight', ['operation' => $operation->id]) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="min-h-11 rounded-xl border border-amber-400 bg-white px-4 py-2 text-sm font-bold text-amber-900">Validar datos sin transmitir</button>
                                </form>
                                <p class="mt-1 text-sm leading-6 text-amber-800">
                                    Ya podés agregar conocimientos hijos y cargar sus líneas de mercadería mientras sigan siendo borradores editables.
                                    Podés vincular contenedores ya registrados desde las líneas con embalaje 05 y condición H/P válida.
                                    La edición y el alta de contenedores ya están disponibles; verificá la documentación CSC/ACEP.
                                    Guardar líneas o vincular contenedores no significa aceptación de Aduana. La operación aduanera requiere confirmación expresa y genera una transacción auditable.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <aside aria-labelledby="steps-heading" class="space-y-5 lg:col-span-4">
                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Guía de la operación</p>
                        <h2 id="steps-heading" class="mt-1 text-xl font-bold text-gray-900">¿Qué sigue ahora?</h2>
                        <ol class="mt-6 space-y-5">
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-sm font-bold text-gray-900">1. Identificar el título madre</p>
                                    <p class="mt-1 text-xs leading-5 text-emerald-700">Referencias guardadas.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-sm font-bold text-indigo-700">2</span>
                                <div>
                                    <p class="text-sm font-bold text-gray-900">2. Cargar conocimientos hijos</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-600">
                                        {{ $operation->bills_of_lading_count > 0
                                            ? 'Ya hay conocimientos guardados. Podés agregar otros. Podés editar sus borradores.'
                                            : 'Podés agregar ahora el primer conocimiento hijo. Quedará guardado como borrador.' }}
                                    </p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-sm font-bold text-gray-500">3</span>
                                <div>
                                    <p class="text-sm font-semibold text-gray-700">3. Completar mercadería y contenedores</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500">Agregá las líneas de mercadería y vinculá o registrá contenedores cuando el embalaje sea 05. Luego utilizá «Validar datos sin transmitir».</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-sm font-bold text-gray-500">4</span>
                                <div>
                                    <p class="text-sm font-semibold text-gray-700">4. Transmitir a Aduana</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500">Seleccioná los conocimientos, elegí Registrar, Rectificar o Eliminar y confirmá la operación. Esta acción sí se transmite a Aduana.</p>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <div class="rounded-lg bg-gray-100 p-5 text-gray-800 shadow-lg sm:p-6">
                        <p class="text-sm font-bold">Tu expediente está guardado</p>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Podés salir y volver más tarde. Las referencias y los conocimientos asociados permanecerán en tu expediente.
                        </p>
                        <a href="{{ route('company.simple.desconsolidado.operations.index') }}"
                           class="mt-5 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-gray-900 transition hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
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
