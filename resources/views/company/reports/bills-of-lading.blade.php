<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📋 Listado de Conocimientos de Embarque
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Genere reportes PDF o Excel de conocimientos filtrados por fecha, cliente o puerto
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
            
            {{-- MENSAJES --}}
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif
            @if(session('info'))
                <div class="mb-6 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                    {{ session('info') }}
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
                            <strong>¿Cómo generar un listado?</strong><br>
                            1. Aplique filtros opcionales (fechas, clientes, puertos, estado)<br>
                            2. Elija el formato deseado (PDF o Excel)<br>
                            3. Haga clic en "Generar Reporte"
                        </p>
                    </div>
                </div>
            </div>

            {{-- FORMULARIO --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-1">
                        Formato actual
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Listado existente en PDF o Excel. Se conserva sin reemplazarlo.
                    </p>

                    <form method="POST" action="{{ route('company.reports.export', 'bills-of-lading') }}">
                        @csrf
                        <input type="hidden" name="filters[template]" value="standard">

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            
                            {{-- RANGO DE FECHAS --}}
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">
                                    Desde (Fecha BL)
                                </label>
                                <input type="date" 
                                       id="date_from" 
                                       name="filters[date_from]"
                                       value="{{ old('filters.date_from') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">
                                    Hasta (Fecha BL)
                                </label>
                                <input type="date" 
                                       id="date_to" 
                                       name="filters[date_to]"
                                       value="{{ old('filters.date_to') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            {{-- ESTADO --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado
                                </label>
                                <select id="status" 
                                        name="filters[status]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los estados</option>
                                    <option value="draft">Borrador</option>
                                    <option value="verified">Verificado</option>
                                    <option value="sent_to_customs">Enviado a Aduana</option>
                                    <option value="customs_approved">Aprobado por Aduana</option>
                                    <option value="in_transit">En Tránsito</option>
                                    <option value="delivered">Entregado</option>
                                </select>
                            </div>

                            {{-- VIAJE --}}
                            <div>
                                <label for="voyage_id" class="block text-sm font-medium text-gray-700">
                                    Viaje
                                </label>
                                <select id="voyage_id"
                                        name="filters[voyage_id]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los viajes</option>
                                    @foreach($filters['voyages'] as $voyage)
                                        <option value="{{ $voyage->id }}">
                                            {{ $voyage->voyage_number }}
                                            @if($voyage->departure_date)
                                                - {{ $voyage->departure_date->format('d/m/Y') }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- PUERTO DE CARGA --}}
                            <div>
                                <label for="loading_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de carga
                                </label>
                                <select id="loading_port_id"
                                        name="filters[loading_port_id]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los puertos de carga</option>
                                    @foreach($filters['ports'] as $port)
                                        <option value="{{ $port->id }}">{{ $port->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- PUERTO DE DESCARGA --}}
                            <div>
                                <label for="discharge_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de descarga
                                </label>
                                <select id="discharge_port_id"
                                        name="filters[discharge_port_id]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los puertos de descarga</option>
                                    @foreach($filters['ports'] as $port)
                                        <option value="{{ $port->id }}">{{ $port->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- PUERTO DE DESTINO --}}
                            <div>
                                <label for="final_destination_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de destino
                                </label>
                                <select id="final_destination_port_id"
                                        name="filters[final_destination_port_id]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los puertos de destino</option>
                                    @foreach($filters['ports'] as $port)
                                        <option value="{{ $port->id }}">{{ $port->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- CARGADOR --}}
                            <div>
                                <label for="shipper_id" class="block text-sm font-medium text-gray-700">
                                    Cargador (opcional)
                                </label>
                                <select id="shipper_id" 
                                        name="filters[shipper_id]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los cargadores</option>
                                    @foreach($filters['shipper'] as $shipperId => $shipperName)
                                        <option value="{{ $shipperId }}">{{ $shipperName }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- CONSIGNATARIO --}}
                            <div>
                                <label for="consignee_id" class="block text-sm font-medium text-gray-700">
                                    Consignatario (opcional)
                                </label>
                                <select id="consignee_id" 
                                        name="filters[consignee_id]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los consignatarios</option>
                                    @foreach($filters['consignee'] as $consigneeId => $consigneeName)
                                        <option value="{{ $consigneeId }}">{{ $consigneeName }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- FORMATO --}}
                            <div>
                                <label for="format" class="block text-sm font-medium text-gray-700">
                                    Formato <span class="text-red-500">*</span>
                                </label>
                                <select id="format" 
                                        name="format" 
                                        required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="pdf">PDF (Recomendado)</option>
                                    <option value="excel">Excel</option>
                                </select>
                            </div>
                        </div>

                        {{-- BOTONES --}}
                        <div class="mt-6 flex items-center justify-end space-x-3">
                            <a href="{{ route('company.reports.index') }}" 
                               class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Generar Reporte
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- FORMATO SEGÚN MUESTRA --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <div class="mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-1">
                            Formato según muestra
                        </h3>
                        <p class="text-sm text-gray-500">
                            PDF en hoja Oficio. Permite seleccionar viaje, puertos, cliente y uno, varios o todos los conocimientos.
                        </p>
                    </div>

                    <form method="GET" action="{{ route('company.reports.bills-of-lading') }}" class="mb-6">
                        <label for="client_voyage_id" class="block text-sm font-medium text-gray-700">
                            Viaje <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1 flex flex-col sm:flex-row gap-3">
                            <select id="client_voyage_id"
                                    name="client_voyage_id"
                                    required
                                    class="block w-full sm:max-w-xl border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Seleccione un viaje</option>
                                @foreach($filters['voyages'] as $voyage)
                                    <option value="{{ $voyage->id }}" {{ (string) request('client_voyage_id') === (string) $voyage->id ? 'selected' : '' }}>
                                        {{ $voyage->voyage_number }}
                                        @if($voyage->departure_date)
                                            - {{ $voyage->departure_date->format('d/m/Y') }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit"
                                    class="inline-flex justify-center items-center px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-medium rounded-md">
                                Cargar conocimientos
                            </button>
                        </div>
                    </form>

                    @if($clientFormatVoyage)
                        @php
                            $clientLoadingPorts = $clientFormatBills->pluck('loadingPort')->filter()->unique('id')->sortBy('name')->values();
                            $clientDischargePorts = $clientFormatBills->pluck('dischargePort')->filter()->unique('id')->sortBy('name')->values();
                            $clientFinalPorts = $clientFormatBills->pluck('finalDestinationPort')->filter()->unique('id')->sortBy('name')->values();
                        @endphp

                        <div class="mb-4 rounded-md bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">
                            <strong>Viaje:</strong> {{ $clientFormatVoyage->voyage_number }}
                            @if($clientFormatVoyage->leadVessel)
                                · <strong>Embarcación:</strong> {{ $clientFormatVoyage->leadVessel->name }}
                            @endif
                            · <strong>Conocimientos:</strong> {{ $clientFormatBills->count() }}
                        </div>

                        <form method="POST"
                              action="{{ route('company.reports.export', 'bills-of-lading') }}"
                              id="client-format-form">
                            @csrf
                            <input type="hidden" name="format" value="pdf">
                            <input type="hidden" name="filters[template]" value="client">
                            <input type="hidden" name="filters[voyage_id]" value="{{ $clientFormatVoyage->id }}">

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 mb-5">
                                <div>
                                    <label for="client_loading_port_id" class="block text-sm font-medium text-gray-700">Puerto de carga</label>
                                    <select id="client_loading_port_id" name="filters[loading_port_id]"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Todos</option>
                                        @foreach($clientLoadingPorts as $port)
                                            <option value="{{ $port->id }}">{{ $port->code ? $port->code . ' - ' : '' }}{{ $port->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="client_discharge_port_id" class="block text-sm font-medium text-gray-700">Puerto de descarga</label>
                                    <select id="client_discharge_port_id" name="filters[discharge_port_id]"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Todos</option>
                                        @foreach($clientDischargePorts as $port)
                                            <option value="{{ $port->id }}">{{ $port->code ? $port->code . ' - ' : '' }}{{ $port->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="client_final_port_id" class="block text-sm font-medium text-gray-700">Destino final</label>
                                    <select id="client_final_port_id" name="filters[final_destination_port_id]"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Todos</option>
                                        @foreach($clientFinalPorts as $port)
                                            <option value="{{ $port->id }}">{{ $port->code ? $port->code . ' - ' : '' }}{{ $port->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                                    <select id="client_id" name="filters[client_id]"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Todos</option>
                                        @foreach($clientFormatClients as $client)
                                            <option value="{{ $client->id }}">{{ $client->commercial_name ?: $client->legal_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <details class="rounded-md border border-gray-200 p-3 mb-5" open>
                                <summary class="cursor-pointer text-sm font-medium text-gray-800">
                                    Conocimientos a incluir ({{ $clientFormatBills->count() }})
                                </summary>
                                <div class="mt-3 max-h-64 overflow-y-auto space-y-2 pr-2">
                                    <label class="flex items-center gap-2 pb-2 border-b border-gray-200 text-sm font-semibold text-gray-700">
                                        <input type="checkbox" id="client-select-all" checked class="rounded border-gray-300">
                                        Seleccionar todos
                                    </label>

                                    @foreach($clientFormatBills as $bill)
                                        <label class="flex items-start gap-2 text-sm text-gray-700">
                                            <input type="checkbox"
                                                   name="filters[bill_ids][]"
                                                   value="{{ $bill->id }}"
                                                   class="client-bill-checkbox mt-1 rounded border-gray-300"
                                                   checked>
                                            <span>
                                                <strong>{{ $bill->bill_number }}</strong>
                                                @if($bill->shipper || $bill->consignee)
                                                    <span class="text-xs text-gray-500">
                                                        — {{ $bill->shipper?->commercial_name ?: $bill->shipper?->legal_name ?: 'Sin cargador' }}
                                                        → {{ $bill->consignee?->commercial_name ?: $bill->consignee?->legal_name ?: 'Sin consignatario' }}
                                                    </span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                                    Generar formato según muestra (Oficio)
                                </button>
                            </div>
                        </form>

                        <script>
                            (() => {
                                const form = document.getElementById('client-format-form');
                                if (!form) return;

                                const selectAll = document.getElementById('client-select-all');
                                const bills = Array.from(form.querySelectorAll('.client-bill-checkbox'));

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

            {{-- ESTADÍSTICAS --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Estadísticas de Conocimientos
                    </h3>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-500">
                            <div class="text-2xl font-bold text-blue-600">{{ $stats['total'] }}</div>
                            <div class="text-sm text-gray-600">Total</div>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-4 border-l-4 border-yellow-500">
                            <div class="text-2xl font-bold text-yellow-600">{{ $stats['this_month'] }}</div>
                            <div class="text-sm text-gray-600">Este mes</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-500">
                            <div class="text-2xl font-bold text-green-600">{{ $stats['pending'] }}</div>
                            <div class="text-sm text-gray-600">Pendientes</div>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-500">
                            <div class="text-2xl font-bold text-purple-600">{{ $stats['completed'] }}</div>
                            <div class="text-sm text-gray-600">Emitidos</div>
                        </div>
                    </div>

                    @if($stats['total'] === 0)
                        <div class="mt-6 text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay conocimientos</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Comience creando conocimientos de embarque en sus viajes.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>