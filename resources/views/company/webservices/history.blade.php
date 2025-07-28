<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📋 Historial de WebServices
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('company.webservices.send') }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring focus:ring-blue-300 disabled:opacity-25 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Nueva Transacción
                </a>
                <button type="button" 
                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                        onclick="document.getElementById('exportModal').classList.remove('hidden')">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Exportar
                </button>
            </div>
        </div>
        <div class="mt-2">
            <p class="text-sm text-gray-600">
                Transacciones aduaneras - {{ $company->legal_name ?? 'MAERSK LINE ARGENTINA S.A' }}
            </p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Dashboard de estadísticas --}}
            @if(isset($statistics) && !empty($statistics))
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-blue-600 rounded-lg shadow p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Total Transacciones</p>
                            <p class="text-3xl font-bold">{{ $statistics['total'] ?? 0 }}</p>
                        </div>
                        <div class="bg-blue-500 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-green-600 rounded-lg shadow p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Exitosas</p>
                            <p class="text-3xl font-bold">{{ $statistics['by_status']['success']['count'] ?? 0 }}</p>
                        </div>
                        <div class="bg-green-500 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-yellow-600 rounded-lg shadow p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm font-medium">Pendientes</p>
                            <p class="text-3xl font-bold">{{ $statistics['by_status']['pending']['count'] ?? 0 }}</p>
                        </div>
                        <div class="bg-yellow-500 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-red-600 rounded-lg shadow p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm font-medium">Con Errores</p>
                            <p class="text-3xl font-bold">{{ $statistics['by_status']['error']['count'] ?? 0 }}</p>
                        </div>
                        <div class="bg-red-500 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Filtros avanzados --}}
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">🔍 Filtros Avanzados</h3>
                        <button type="button" 
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                id="toggleFilters">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                            </svg>
                            Filtros
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4" id="filtersPanel" style="display: {{ !empty(array_filter($filters ?? [])) ? 'block' : 'none' }}">
                    <form method="GET" action="{{ route('company.webservices.history') }}" id="filtersForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            {{-- Búsqueda general --}}
                            <div class="lg:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">🔍 Búsqueda General</label>
                                <input type="text" 
                                       id="search" 
                                       name="search" 
                                       value="{{ $filters['search'] ?? '' }}"
                                       placeholder="Transaction ID, referencia, confirmación..."
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">Ej: V022NB, PAR13001, TX-2024-001</p>
                            </div>

                            {{-- Tipo de webservice --}}
                            <div>
                                <label for="webservice_type" class="block text-sm font-medium text-gray-700 mb-1">📋 Tipo</label>
                                <select id="webservice_type" name="webservice_type" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="all">Todos los tipos</option>
                                    <option value="anticipada" {{ ($filters['webservice_type'] ?? '') === 'anticipada' ? 'selected' : '' }}>
                                        Información Anticipada
                                    </option>
                                    <option value="micdta" {{ ($filters['webservice_type'] ?? '') === 'micdta' ? 'selected' : '' }}>
                                        MIC/DTA
                                    </option>
                                    <option value="desconsolidados" {{ ($filters['webservice_type'] ?? '') === 'desconsolidados' ? 'selected' : '' }}>
                                        Desconsolidados
                                    </option>
                                    <option value="transbordos" {{ ($filters['webservice_type'] ?? '') === 'transbordos' ? 'selected' : '' }}>
                                        Transbordos
                                    </option>
                                    <option value="paraguay" {{ ($filters['webservice_type'] ?? '') === 'paraguay' ? 'selected' : '' }}>
                                        Paraguay
                                    </option>
                                </select>
                            </div>

                            {{-- Estado --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">📊 Estado</label>
                                <select id="status" name="status" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="all">Todos los estados</option>
                                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                                    <option value="validating" {{ ($filters['status'] ?? '') === 'validating' ? 'selected' : '' }}>Validando</option>
                                    <option value="sending" {{ ($filters['status'] ?? '') === 'sending' ? 'selected' : '' }}>Enviando</option>
                                    <option value="sent" {{ ($filters['status'] ?? '') === 'sent' ? 'selected' : '' }}>Enviado</option>
                                    <option value="success" {{ ($filters['status'] ?? '') === 'success' ? 'selected' : '' }}>Exitoso</option>
                                    <option value="error" {{ ($filters['status'] ?? '') === 'error' ? 'selected' : '' }}>Error</option>
                                    <option value="retry" {{ ($filters['status'] ?? '') === 'retry' ? 'selected' : '' }}>Reintento</option>
                                    <option value="cancelled" {{ ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                                </select>
                            </div>

                            {{-- País --}}
                            <div>
                                <label for="country" class="block text-sm font-medium text-gray-700 mb-1">🌍 País</label>
                                <select id="country" name="country" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="all">Todos los países</option>
                                    <option value="AR" {{ ($filters['country'] ?? '') === 'AR' ? 'selected' : '' }}>🇦🇷 Argentina</option>
                                    <option value="PY" {{ ($filters['country'] ?? '') === 'PY' ? 'selected' : '' }}>🇵🇾 Paraguay</option>
                                </select>
                            </div>

                            {{-- Ambiente --}}
                            <div>
                                <label for="environment" class="block text-sm font-medium text-gray-700 mb-1">⚙️ Ambiente</label>
                                <select id="environment" name="environment" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="all">Todos</option>
                                    <option value="testing" {{ ($filters['environment'] ?? '') === 'testing' ? 'selected' : '' }}>Testing</option>
                                    <option value="production" {{ ($filters['environment'] ?? '') === 'production' ? 'selected' : '' }}>Producción</option>
                                </select>
                            </div>

                            {{-- Viaje/Barcaza --}}
                            <div>
                                <label for="voyage_number" class="block text-sm font-medium text-gray-700 mb-1">🚢 Viaje/Barcaza</label>
                                <input type="text" 
                                       id="voyage_number" 
                                       name="voyage_number" 
                                       value="{{ $filters['voyage_number'] ?? $filters['voyage_code'] ?? '' }}"
                                       placeholder="V022NB, PAR13001..."
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">Número de viaje o referencia interna</p>
                            </div>

                            {{-- Fecha desde --}}
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">📅 Desde</label>
                                <input type="date" 
                                       id="date_from" 
                                       name="date_from" 
                                       value="{{ $filters['date_from'] ?? '' }}"
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>

                            {{-- Fecha hasta --}}
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">📅 Hasta</label>
                                <input type="date" 
                                       id="date_to" 
                                       name="date_to" 
                                       value="{{ $filters['date_to'] ?? '' }}"
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="requires_action" 
                                       name="requires_action" 
                                       value="1"
                                       {{ !empty($filters['requires_action']) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="requires_action" class="ml-2 block text-sm text-gray-900">
                                    ⚠️ Solo transacciones que requieren acción
                                </label>
                            </div>

                            <div class="flex space-x-3">
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    Aplicar Filtros
                                </button>
                                <a href="{{ route('company.webservices.history') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Limpiar
                                </a>
                            </div>
                        </div>

                        @if(!empty(array_filter($filters ?? [])))
                        <div class="mt-3 text-right">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Filtros activos: {{ count(array_filter($filters)) }}
                            </span>
                        </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Tabla de transacciones --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">📊 Transacciones ({{ $transactions->total() ?? 0 }})</h3>
                        <div class="relative">
                            <button type="button" 
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    id="sortDropdown">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                </svg>
                                Ordenar
                            </button>
                            <div class="hidden absolute right-0 z-10 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" id="sortMenu">
                                <div class="py-1">
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_direction' => 'desc']) }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">📅 Más recientes</a>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_direction' => 'asc']) }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">📅 Más antiguos</a>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'status', 'sort_direction' => 'asc']) }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">📊 Por estado</a>
                                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'webservice_type', 'sort_direction' => 'asc']) }}" 
                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">📋 Por tipo</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">País</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Viaje/Barcaza</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Confirmación</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($transactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                {{-- Transaction ID --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono font-medium text-gray-900">{{ $transaction->transaction_id }}</div>
                                    @if($transaction->external_reference)
                                        <div class="text-xs text-gray-500">{{ $transaction->external_reference }}</div>
                                    @endif
                                </td>

                                {{-- Tipo --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $transaction->webservice_type }}
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">{{ $transaction->environment }}</div>
                                </td>

                                {{-- País --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($transaction->country === 'AR')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            🇦🇷 AR
                                        </span>
                                    @elseif($transaction->country === 'PY')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            🇵🇾 PY
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $transaction->country }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Estado --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'validating' => 'bg-blue-100 text-blue-800',
                                            'sending' => 'bg-indigo-100 text-indigo-800',
                                            'sent' => 'bg-purple-100 text-purple-800', 
                                            'success' => 'bg-green-100 text-green-800',
                                            'error' => 'bg-red-100 text-red-800',
                                            'retry' => 'bg-orange-100 text-orange-800',
                                            'cancelled' => 'bg-gray-100 text-gray-800',
                                            'expired' => 'bg-gray-100 text-gray-800'
                                        ];
                                        $colorClass = $statusColors[$transaction->status] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                                        {{ ucfirst($transaction->status) }}
                                    </span>
                                    @if($transaction->retry_count > 0)
                                        <div class="text-xs text-gray-500 mt-1">Reintento: {{ $transaction->retry_count }}</div>
                                    @endif
                                </td>

                                {{-- Viaje/Barcaza --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($transaction->voyage)
                                        <div class="text-sm text-gray-900">
                                            <div class="font-medium">{{ $transaction->voyage->voyage_number ?? $transaction->voyage->voyage_code ?? 'N/A' }}</div>
                                            @if($transaction->voyage->internal_reference ?? $transaction->voyage->barge_name ?? null)
                                                <div class="text-gray-500 text-xs">{{ $transaction->voyage->internal_reference ?? $transaction->voyage->barge_name }}</div>
                                            @endif
                                        </div>
                                    @elseif($transaction->shipment)
                                        <div class="text-sm text-gray-500">
                                            Shipment: {{ $transaction->shipment->shipment_number }}
                                        </div>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                {{-- Confirmación --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($transaction->confirmation_number)
                                        <div class="text-sm font-mono text-gray-900">{{ $transaction->confirmation_number }}</div>
                                    @elseif($transaction->response && $transaction->response->confirmation_number)
                                        <div class="text-sm font-mono text-gray-900">{{ $transaction->response->confirmation_number }}</div>
                                    @else
                                        <span class="text-gray-400">Pendiente</span>
                                    @endif
                                    
                                    @if($transaction->response && $transaction->response->requires_action)
                                        <div class="mt-1">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                                Requiere Acción
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                {{-- Usuario --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($transaction->user)
                                        {{ $transaction->user->name }}
                                    @else
                                        <span class="text-gray-400">Sistema</span>
                                    @endif
                                </td>

                                {{-- Fecha --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>{{ $transaction->created_at->format('d/m/Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $transaction->created_at->format('H:i') }}</div>
                                    @if($transaction->sent_at)
                                        <div class="text-xs text-green-600 mt-1">
                                            Enviado: {{ $transaction->sent_at->format('H:i') }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Acciones --}}
                               <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        {{-- Ver detalles --}}
                                        <a href="{{ route('company.webservices.show-webservice', $transaction) }}" 
                                        class="text-indigo-600 hover:text-indigo-900">
                                            Ver
                                        </a>

                                        {{-- BOTÓN ENVIAR AHORA - Solo para pending --}}
                                        @if($transaction->status === 'pending')
                                            <form method="POST" 
                                                action="{{ route('company.webservices.send-pending-transaction', $transaction) }}" 
                                                style="display: inline;"
                                                onsubmit="return confirm('¿Confirma enviar {{ $transaction->transaction_id }}?')">
                                                @csrf
                                                <button type="submit" 
                                                        class="text-green-600 hover:text-green-900 text-xs font-medium">
                                                    🚀 Enviar
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Botón Reintentar - Solo para error --}}
                                        @if($transaction->status === 'error' && ($transaction->can_retry ?? false))
                                            <form method="POST" 
                                                action="{{ route('company.webservices.retry-transaction', $transaction) }}" 
                                                style="display: inline;"
                                                onsubmit="return confirm('¿Reintentar {{ $transaction->transaction_id }}?')">
                                                @csrf
                                                <button type="submit" 
                                                        class="text-orange-600 hover:text-orange-900 text-xs font-medium">
                                                    ↻ Retry
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Descargar XML --}}
                                        @if($transaction->response_xml)
                                            <a href="{{ route('company.webservices.download-xml', $transaction) }}" 
                                            class="text-blue-600 hover:text-blue-900 text-xs">
                                                XML
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginación --}}
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        @if($transactions->onFirstPage())
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-white cursor-default">
                                Anterior
                            </span>
                        @else
                            <a href="{{ $transactions->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Anterior
                            </a>
                        @endif

                        @if($transactions->hasMorePages())
                            <a href="{{ $transactions->nextPageUrl() }}" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Siguiente
                            </a>
                        @else
                            <span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-white cursor-default">
                                Siguiente
                            </span>
                        @endif
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Mostrando
                                <span class="font-medium">{{ $transactions->firstItem() }}</span>
                                a
                                <span class="font-medium">{{ $transactions->lastItem() }}</span>
                                de
                                <span class="font-medium">{{ $transactions->total() }}</span>
                                transacciones
                            </p>
                        </div>
                        <div>
                            {{ $transactions->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
                @else
                {{-- Estado vacío --}}
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8l-4 4 4 4M7 7l4 4-4 4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No se encontraron transacciones</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if(!empty(array_filter($filters ?? [])))
                            No hay transacciones que coincidan con los filtros aplicados.
                        @else
                            Aún no has enviado ninguna transacción webservice.
                        @endif
                    </p>
                    <div class="mt-6">
                        @if(!empty(array_filter($filters ?? [])))
                            <a href="{{ route('company.webservices.history') }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Limpiar filtros
                            </a>
                        @else
                            <a href="{{ route('company.webservices.send') }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Enviar primera transacción
                            </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal de exportación --}}
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" id="exportModal">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">📥 Exportar Historial</h3>
                    <button type="button" 
                            class="text-gray-400 hover:text-gray-600"
                            onclick="document.getElementById('exportModal').classList.add('hidden')">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('company.webservices.export') }}">
                    @csrf
                    <div class="mb-4">
                        <label for="export_format" class="block text-sm font-medium text-gray-700 mb-2">Formato</label>
                        <select id="export_format" name="format" 
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="excel">📊 Excel (.xlsx)</option>
                            <option value="csv">📄 CSV</option>
                            <option value="pdf">📋 PDF</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="export_period" class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                        <select id="export_period" name="period" 
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="current_filters">Filtros actuales</option>
                            <option value="last_30_days">Últimos 30 días</option>
                            <option value="last_3_months">Últimos 3 meses</option>
                            <option value="current_year">Año actual</option>
                            <option value="all">Todas las transacciones</option>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" name="include_xml" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-900">Incluir XML de respuestas</span>
                        </label>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                onclick="document.getElementById('exportModal').classList.add('hidden')">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Exportar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle filtros
        document.getElementById('toggleFilters')?.addEventListener('click', function() {
            const panel = document.getElementById('filtersPanel');
            const isVisible = panel.style.display !== 'none';
            panel.style.display = isVisible ? 'none' : 'block';
        });

        // Toggle sort dropdown
        document.getElementById('sortDropdown')?.addEventListener('click', function(e) {
            e.preventDefault();
            const menu = document.getElementById('sortMenu');
            menu.classList.toggle('hidden');
        });

        // Close sort dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('sortDropdown');
            const menu = document.getElementById('sortMenu');
            if (dropdown && menu && !dropdown.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // Auto-submit en selects importantes
        const autoSubmitFields = ['status', 'webservice_type', 'country'];
        autoSubmitFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                field.addEventListener('change', function() {
                    setTimeout(() => {
                        document.getElementById('filtersForm').submit();
                    }, 100);
                });
            }
        });

        // Toggle download menus
        function toggleDownloadMenu(transactionId) {
            const menu = document.getElementById('downloadMenu' + transactionId);
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        // Close download menus when clicking outside
        document.addEventListener('click', function(e) {
            const downloadMenus = document.querySelectorAll('[id^="downloadMenu"]');
            downloadMenus.forEach(menu => {
                if (!menu.contains(e.target) && !e.target.closest('[onclick*="toggleDownloadMenu"]')) {
                    menu.classList.add('hidden');
                }
            });
        });

        // Búsqueda con debouncing
        const searchInput = document.getElementById('search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length >= 3 || query.length === 0) {
                    searchTimeout = setTimeout(() => {
                        document.getElementById('filtersForm').submit();
                    }, 800);
                }
            });
        }

        // Validación de fechas
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        
        if (dateFrom && dateTo) {
            dateFrom.addEventListener('change', function() {
                if (this.value && dateTo.value && this.value > dateTo.value) {
                    alert('La fecha "desde" no puede ser mayor que la fecha "hasta"');
                    this.value = '';
                }
            });
            
            dateTo.addEventListener('change', function() {
                if (this.value && dateFrom.value && this.value < dateFrom.value) {
                    alert('La fecha "hasta" no puede ser menor que la fecha "desde"');
                    this.value = '';
                }
            });
        }

        // Refresh automático cada 30 segundos si hay transacciones en proceso
        const hasProcessingTransactions = document.querySelector('.bg-yellow-100, .bg-blue-100, .bg-indigo-100');
        if (hasProcessingTransactions) {
            setTimeout(() => {
                if (document.visibilityState === 'visible' && !document.activeElement.matches('input, select, textarea')) {
                    window.location.reload();
                }
            }, 30000);
        }
    </script>
</x-app-layout>