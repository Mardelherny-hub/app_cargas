<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📄 Manifiestos de Carga
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Genere reportes PDF o Excel de manifiestos por viaje
                </p>
            </div>
            <a href="{{ route('company.reports.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                ← Volver a Reportes
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- MENSAJES DE FEEDBACK --}}
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if(session('info'))
                <div class="mb-6 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('info') }}</span>
                </div>
            @endif

            {{-- INSTRUCCIONES --}}
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>¿Cómo generar un manifiesto?</strong><br>
                            1. Elija Formato actual o Cargo Manifest<br>
                            2. Seleccione un viaje<br>
                            3. Genere el reporte o cargue los conocimientos para Cargo Manifest
                        </p>
                    </div>
                </div>
            </div>

            {{-- FORMULARIO DE GENERACIÓN --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-1">
                        Generar manifiesto
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Elija el formato y el viaje. Cargo Manifest permite seleccionar conocimientos y filtrar por puertos.
                    </p>

                    <form method="POST" action="{{ route('company.reports.export', 'manifests') }}"
                          id="manifest-report-form" data-load-url="{{ route('company.reports.manifests') }}">
                        @csrf
                        <label for="manifest-presentation" class="block text-sm font-medium text-gray-700">Formato de reporte</label>
                        <select id="manifest-presentation" name="filters[template]"
                                class="mt-1 mb-6 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="standard" {{ request()->filled('client_voyage_id') ? '' : 'selected' }}>Formato actual</option>
                            <option value="client" {{ request()->filled('client_voyage_id') ? 'selected' : '' }}>Cargo Manifest</option>
                        </select>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- SELECCIONAR VIAJE --}}
                            <div class="sm:col-span-2">
                                <label for="voyage_id" class="block text-sm font-medium text-gray-700">
                                    Viaje <span class="text-red-500">*</span>
                                </label>
                                <select id="voyage_id" 
                                        name="filters[voyage_id]" 
                                        required
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                        onchange="updateVoyageDetails(this)">
                                    <option value="">Seleccione un viaje...</option>
                                    @forelse($reportVoyages as $voyage)
                                        <option value="{{ $voyage->id }}"
                                                {{ (string) request('client_voyage_id') === (string) $voyage->id ? 'selected' : '' }}
                                                data-vessel="{{ $voyage->leadVessel->name ?? 'N/A' }}"
                                                data-origin="{{ $voyage->originPort->name ?? 'N/A' }}"
                                                data-destination="{{ $voyage->destinationPort->name ?? 'N/A' }}"
                                                data-bills="{{ $voyage->bills_of_lading_count }}"
                                                data-departure="{{ $voyage->departure_date ? \Carbon\Carbon::parse($voyage->departure_date)->format('d/m/Y') : 'N/A' }}">
                                            {{ $voyage->voyage_number }} - {{ $voyage->leadVessel->name ?? 'N/A' }}
                                            ({{ $voyage->bills_of_lading_count }} BLs)
                                        </option>
                                    @empty
                                        <option value="" disabled>No hay viajes disponibles</option>
                                    @endforelse
                                </select>
                                <p class="mt-2 text-sm text-gray-500">
                                    Solo se muestran viajes con conocimientos de embarque
                                </p>
                            </div>

                            {{-- DETALLES DEL VIAJE SELECCIONADO --}}
                            <div id="voyage-details" class="sm:col-span-2 bg-gray-50 rounded-lg p-4 hidden">
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Detalles del viaje:</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-600">Embarcación:</span>
                                        <span id="detail-vessel" class="font-medium ml-2">-</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Conocimientos:</span>
                                        <span id="detail-bills" class="font-medium ml-2">-</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Origen:</span>
                                        <span id="detail-origin" class="font-medium ml-2">-</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Destino:</span>
                                        <span id="detail-destination" class="font-medium ml-2">-</span>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="text-gray-600">Fecha Salida:</span>
                                        <span id="detail-departure" class="font-medium ml-2">-</span>
                                    </div>
                                </div>
                            </div>

                            {{-- FORMATO --}}
                            <div id="manifest-file-options">
                                <label for="format" class="block text-sm font-medium text-gray-700">
                                    Tipo de archivo <span class="text-red-500">*</span>
                                </label>
                                <select id="format" 
                                        name="format" 
                                        required
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option value="pdf">PDF (Recomendado)</option>
                                    <option value="excel">Excel</option>
                                </select>
                            </div>

                            {{-- FILTROS OPCIONALES --}}
                            <div id="manifest-status-options">
                                <label for="status_filter" class="block text-sm font-medium text-gray-700">
                                    Estado BL (opcional)
                                </label>
                                <select id="status_filter" 
                                        name="filters[status]" 
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                    <option value="">Todos los estados</option>
                                    <option value="draft">Borrador</option>
                                    <option value="verified">Verificado</option>
                                    <option value="sent_to_customs">Enviado a Aduana</option>
                                </select>
                            </div>
                        </div>

                        {{-- BOTONES --}}
                        <div class="mt-6 flex items-center justify-end space-x-3">
                            <a href="{{ route('company.reports.index') }}" 
                               class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span id="manifest-submit-label">Generar Reporte</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- CARGO MANIFEST --}}
            <div id="manifest-cargo-options" data-voyage-id="{{ $clientManifestVoyage?->id }}" @if(!request()->filled('client_voyage_id')) hidden @endif class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-1">
                        Cargo Manifest
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">
                        PDF A4 apaisado. Permite seleccionar viaje, puertos y uno, varios o todos los conocimientos.
                    </p>

                    @if($clientManifestVoyage)
                        @php
                            $manifestLoadingPorts = $clientManifestBills->pluck('loadingPort')->filter()->unique('id')->sortBy('name')->values();
                            $manifestDischargePorts = $clientManifestBills->pluck('dischargePort')->filter()->unique('id')->sortBy('name')->values();
                            $manifestFinalPorts = $clientManifestBills->pluck('finalDestinationPort')->filter()->unique('id')->sortBy('name')->values();
                        @endphp

                        <div class="mb-4 rounded-md bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">
                            <strong>Viaje:</strong> {{ $clientManifestVoyage->voyage_number }}
                            @if($clientManifestVoyage->leadVessel)
                                · <strong>Embarcación:</strong> {{ $clientManifestVoyage->leadVessel->name }}
                            @endif
                            · <strong>Conocimientos:</strong> {{ $clientManifestBills->count() }}
                        </div>

                        <form method="POST"
                              action="{{ route('company.reports.export', 'manifests') }}"
                              id="manifest-client-form">
                            @csrf
                            <input type="hidden" name="format" value="pdf">
                            <input type="hidden" name="filters[template]" value="client">
                            <input type="hidden" name="filters[voyage_id]" value="{{ $clientManifestVoyage->id }}">

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-5">
                                <div>
                                    <label for="manifest_loading_port_id" class="block text-sm font-medium text-gray-700">Puerto de carga</label>
                                    <select id="manifest_loading_port_id" name="filters[loading_port_id]"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Todos</option>
                                        @foreach($manifestLoadingPorts as $port)
                                            <option value="{{ $port->id }}">{{ $port->code ? $port->code . ' - ' : '' }}{{ $port->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="manifest_discharge_port_id" class="block text-sm font-medium text-gray-700">Puerto de descarga</label>
                                    <select id="manifest_discharge_port_id" name="filters[discharge_port_id]"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Todos</option>
                                        @foreach($manifestDischargePorts as $port)
                                            <option value="{{ $port->id }}">{{ $port->code ? $port->code . ' - ' : '' }}{{ $port->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="manifest_final_port_id" class="block text-sm font-medium text-gray-700">Destino final</label>
                                    <select id="manifest_final_port_id" name="filters[final_destination_port_id]"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Todos</option>
                                        @foreach($manifestFinalPorts as $port)
                                            <option value="{{ $port->id }}">{{ $port->code ? $port->code . ' - ' : '' }}{{ $port->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <details class="rounded-md border border-gray-200 p-3 mb-5" open>
                                <summary class="cursor-pointer text-sm font-medium text-gray-800">
                                    Conocimientos a incluir ({{ $clientManifestBills->count() }})
                                </summary>
                                <div class="mt-3 max-h-64 overflow-y-auto space-y-2 pr-2">
                                    <label class="flex items-center gap-2 pb-2 border-b border-gray-200 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" id="manifest-select-all" checked class="rounded border-gray-300">
                                        Seleccionar todos
                                    </label>

                                    @foreach($clientManifestBills as $bill)
                                        <label class="flex items-start gap-2 text-sm text-gray-700">
                                            <input type="checkbox"
                                                   name="filters[bill_ids][]"
                                                   value="{{ $bill->id }}"
                                                   class="manifest-bill-checkbox mt-1 rounded border-gray-300"
                                                   checked>
                                            <span>
                                                <strong>{{ $bill->bill_number }}</strong>
                                                <span class="text-xs text-gray-500">
                                                    — {{ $bill->loadingPort?->name ?? 'Sin puerto de carga' }}
                                                    → {{ $bill->dischargePort?->name ?? 'Sin puerto de descarga' }}
                                                    @if($bill->finalDestinationPort)
                                                        → {{ $bill->finalDestinationPort->name }}
                                                    @endif
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                                    Generar Cargo Manifest
                                </button>
                            </div>
                        </form>

                        <script>
                            (() => {
                                const form = document.getElementById('manifest-client-form');
                                if (!form) return;

                                const selectAll = document.getElementById('manifest-select-all');
                                const bills = Array.from(form.querySelectorAll('.manifest-bill-checkbox'));

                                selectAll.addEventListener('change', () => {
                                    bills.forEach((checkbox) => checkbox.checked = selectAll.checked);
                                });

                                bills.forEach((checkbox) => {
                                    checkbox.addEventListener('change', () => {
                                        selectAll.checked = bills.every((item) => item.checked);
                                    });
                                });

                                form.addEventListener('submit', (event) => {
                                    if (!bills.some((checkbox) => checkbox.checked)) {
                                        event.preventDefault();
                                        alert('Seleccione al menos un conocimiento.');
                                    }
                                });
                            })();
                        </script>
                    @elseif(request()->filled('client_voyage_id'))
                        <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4 text-sm text-yellow-800">
                            El viaje seleccionado no está disponible para esta empresa o no tiene conocimientos.
                        </div>
                    @endif
                </div>
            </div>

            {{-- LISTADO DE VIAJES DISPONIBLES --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Viajes Disponibles ({{ $reportVoyages->count() }})
                    </h3>

                    @if($reportVoyages->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Viaje
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Embarcación
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Ruta
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            BLs
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Salida
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($reportVoyages as $voyage)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $voyage->voyage_number }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $voyage->leadVessel->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $voyage->originPort->name ?? 'N/A' }} → {{ $voyage->destinationPort->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $voyage->bills_of_lading_count }} BLs
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $voyage->departure_date ? \Carbon\Carbon::parse($voyage->departure_date)->format('d/m/Y') : 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay viajes disponibles</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Los viajes deben tener al menos un conocimiento de embarque para generar manifiestos.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- JAVASCRIPT --}}
    <script>
        // La elección de formato reutiliza los dos endpoints existentes.
        (() => {
            const form = document.getElementById('manifest-report-form');
            const presentation = document.getElementById('manifest-presentation');
            const voyage = document.getElementById('voyage_id');
            const cargo = document.getElementById('manifest-cargo-options');
            const fileFormat = document.getElementById('format');
            const status = document.getElementById('status_filter');

            function refreshPresentation() {
                const isCargo = presentation.value === 'client';
                document.getElementById('manifest-file-options').hidden = isCargo;
                document.getElementById('manifest-status-options').hidden = isCargo;
                fileFormat.disabled = isCargo;
                status.disabled = isCargo;
                cargo.hidden = !isCargo || (cargo.dataset.voyageId !== '' && cargo.dataset.voyageId !== voyage.value);
                document.getElementById('manifest-submit-label').textContent =
                    isCargo ? 'Cargar conocimientos' : 'Generar Reporte';
            }

            presentation.addEventListener('change', refreshPresentation);
            voyage.addEventListener('change', refreshPresentation);
            form.addEventListener('submit', (event) => {
                if (presentation.value !== 'client') return;
                event.preventDefault();
                const url = new URL(form.dataset.loadUrl, window.location.href);
                url.searchParams.set('client_voyage_id', voyage.value);
                window.location.assign(url.toString());
            });
            refreshPresentation();
            updateVoyageDetails(voyage);
        })();

        function updateVoyageDetails(select) {
            const option = select.options[select.selectedIndex];
            const detailsDiv = document.getElementById('voyage-details');
            
            if (option.value) {
                document.getElementById('detail-vessel').textContent = option.dataset.vessel;
                document.getElementById('detail-origin').textContent = option.dataset.origin;
                document.getElementById('detail-destination').textContent = option.dataset.destination;
                document.getElementById('detail-bills').textContent = option.dataset.bills + ' conocimientos';
                document.getElementById('detail-departure').textContent = option.dataset.departure;
                detailsDiv.classList.remove('hidden');
            } else {
                detailsDiv.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>