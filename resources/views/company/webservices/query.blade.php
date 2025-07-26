<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Consultar Estado de Manifiestos') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Consulte el estado de manifiestos enviados a webservices aduaneros - {{ $company->legal_name }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('company.webservices.history') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Ver Historial
                </a>
                <a href="{{ route('company.webservices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver al Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Estadísticas Rápidas --}}
            @if(isset($filterData['quick_stats']))
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Resumen de Actividad - {{ $company->legal_name }}</h3>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-3 bg-blue-50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">{{ $filterData['quick_stats']['total_transactions'] }}</div>
                                <div class="text-sm text-blue-600">Total Transacciones</div>
                            </div>
                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">{{ $filterData['quick_stats']['last_30_days'] }}</div>
                                <div class="text-sm text-green-600">Últimos 30 días</div>
                            </div>
                            <div class="text-center p-3 bg-purple-50 rounded-lg">
                                <div class="text-2xl font-bold text-purple-600">{{ $filterData['quick_stats']['success_rate'] }}%</div>
                                <div class="text-sm text-purple-600">Tasa de Éxito</div>
                            </div>
                            <div class="text-center p-3 bg-yellow-50 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600">{{ $filterData['quick_stats']['pending_queries'] }}</div>
                                <div class="text-sm text-yellow-600">Pendientes</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Formulario de Consulta --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900">Parámetros de Consulta</h3>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">
                        Seleccione los criterios para consultar el estado de sus manifiestos
                    </p>
                </div>

                <form method="POST" action="{{ route('company.webservices.process-query') }}" id="queryForm" class="px-6 py-4">
                    @csrf

                    {{-- Tipo de Consulta --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Tipo de Consulta *</label>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                            @php 
                                $queryTypes = [
                                    'all' => ['name' => 'Todas las transacciones', 'icon' => 'collection'],
                                    'by_transaction' => ['name' => 'Por ID de transacción', 'icon' => 'identification'],
                                    'by_reference' => ['name' => 'Por referencia externa', 'icon' => 'document-text'],
                                    'by_voyage' => ['name' => 'Por viaje/barcaza', 'icon' => 'truck'],
                                    'by_date_range' => ['name' => 'Por rango de fechas', 'icon' => 'calendar']
                                ];
                            @endphp

                            @foreach($queryTypes as $value => $type)
                                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none">
                                    <input type="radio" name="query_type" value="{{ $value }}" 
                                           class="sr-only query-type-radio" 
                                           {{ old('query_type', 'all') === $value ? 'checked' : '' }}
                                           onchange="toggleQueryFields()">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <svg class="w-5 h-5 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                @if($type['icon'] === 'collection')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14-7H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2z"/>
                                                @elseif($type['icon'] === 'identification')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                                @elseif($type['icon'] === 'document-text')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                @elseif($type['icon'] === 'truck')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                                                @elseif($type['icon'] === 'calendar')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                @endif
                                            </svg>
                                            <span class="block text-sm font-medium text-gray-900">{{ $type['name'] }}</span>
                                        </span>
                                    </span>
                                    <span class="radio-indicator absolute -inset-px rounded-lg border-2 pointer-events-none" aria-hidden="true"></span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Configuración País y Ambiente --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700">País *</label>
                            <select name="country" id="country" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" 
                                    required>
                                <option value="AR" {{ old('country', 'AR') === 'AR' ? 'selected' : '' }}>🇦🇷 Argentina (AFIP)</option>
                                <option value="PY" {{ old('country') === 'PY' ? 'selected' : '' }}>🇵🇾 Paraguay (DNA)</option>
                            </select>
                        </div>

                        <div>
                            <label for="environment" class="block text-sm font-medium text-gray-700">Ambiente *</label>
                            <select name="environment" id="environment" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" 
                                    required>
                                <option value="testing" {{ old('environment', 'testing') === 'testing' ? 'selected' : '' }}>🧪 Testing (Homologación)</option>
                                <option value="production" {{ old('environment') === 'production' ? 'selected' : '' }}>🚀 Producción</option>
                            </select>
                        </div>

                        <div>
                            <label for="webservice_type" class="block text-sm font-medium text-gray-700">Tipo de Webservice</label>
                            <select name="webservice_type" id="webservice_type" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Todos los tipos</option>
                                <option value="anticipada" {{ old('webservice_type') === 'anticipada' ? 'selected' : '' }}>Información Anticipada</option>
                                <option value="micdta" {{ old('webservice_type') === 'micdta' ? 'selected' : '' }}>MIC/DTA</option>
                                <option value="desconsolidados" {{ old('webservice_type') === 'desconsolidados' ? 'selected' : '' }}>Desconsolidados</option>
                                <option value="transbordos" {{ old('webservice_type') === 'transbordos' ? 'selected' : '' }}>Transbordos</option>
                            </select>
                        </div>
                    </div>

                    {{-- Campos de Consulta Específicos --}}
                    <div id="specific-fields" class="space-y-6">
                        
                        {{-- Por ID de Transacción --}}
                        <div id="transaction-fields" class="query-field hidden">
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <h4 class="text-md font-medium text-blue-900 mb-3">Consulta por ID de Transacción</h4>
                                <div>
                                    <label for="transaction_id" class="block text-sm font-medium text-gray-700">ID de Transacción</label>
                                    <input type="text" name="transaction_id" id="transaction_id" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                           placeholder="Ej: AR30123456789202507251234567"
                                           value="{{ old('transaction_id') }}">
                                </div>
                            </div>
                        </div>

                        {{-- Por Referencia Externa --}}
                        <div id="reference-fields" class="query-field hidden">
                            <div class="p-4 bg-green-50 rounded-lg">
                                <h4 class="text-md font-medium text-green-900 mb-3">Consulta por Referencia Externa</h4>
                                <div>
                                    <label for="external_reference" class="block text-sm font-medium text-gray-700">Referencia Externa</label>
                                    <input type="text" name="external_reference" id="external_reference" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                           placeholder="Número de confirmación o referencia AFIP"
                                           value="{{ old('external_reference') }}">
                                </div>
                            </div>
                        </div>

                        {{-- Por Viaje/Barcaza --}}
                        <div id="voyage-fields" class="query-field hidden">
                            <div class="p-4 bg-purple-50 rounded-lg">
                                <h4 class="text-md font-medium text-purple-900 mb-3">Consulta por Viaje/Barcaza</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="voyage_code" class="block text-sm font-medium text-gray-700">Código de Viaje</label>
                                        <input type="text" name="voyage_code" id="voyage_code" 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                               placeholder="Ej: V022NB"
                                               value="{{ old('voyage_code') }}"
                                               list="voyage-codes">
                                        <datalist id="voyage-codes">
                                            <option value="V022NB">V022NB</option>
                                            <option value="V023NB">V023NB</option>
                                            <option value="V024NB">V024NB</option>
                                            <option value="V025NB">V025NB</option>
                                        </datalist>
                                    </div>
                                    <div>
                                        <label for="voyage_id" class="block text-sm font-medium text-gray-700">O seleccionar Viaje</label>
                                        <select name="voyage_id" id="voyage_id" 
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm voyage-select">
                                            <option value="">Seleccionar viaje...</option>
                                            @if(isset($filterData['voyage_codes']) && $filterData['voyage_codes']->isNotEmpty())
                                                @foreach($filterData['voyage_codes'] as $voyage)
                                                    <option value="{{ $voyage->id }}" 
                                                            data-voyage-code="{{ $voyage->voyage_code }}" 
                                                            data-barge="{{ $voyage->barge_name }}"
                                                            data-departure="{{ $voyage->departure_port ?? '' }}"
                                                            data-arrival="{{ $voyage->arrival_port ?? '' }}">
                                                        {{ $voyage->voyage_code }} - {{ $voyage->barge_name }}
                                                        @if($voyage->departure_port && $voyage->arrival_port)
                                                            ({{ $voyage->departure_port }} → {{ $voyage->arrival_port }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            @else
                                                {{-- Datos reales de PARANA.csv como fallback --}}
                                                <option value="1" data-voyage-code="V022NB" data-barge="PAR13001" data-departure="ARBUE" data-arrival="PYTVT">V022NB - PAR13001 (ARBUE → PYTVT)</option>
                                                <option value="2" data-voyage-code="V023NB" data-barge="PAR13002" data-departure="ARBUE" data-arrival="PYTVT">V023NB - PAR13002 (ARBUE → PYTVT)</option>
                                                <option value="3" data-voyage-code="V024NB" data-barge="PAR13003" data-departure="ARBUE" data-arrival="PYTVT">V024NB - PAR13003 (ARBUE → PYTVT)</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Por Rango de Fechas --}}
                        <div id="date-fields" class="query-field hidden">
                            <div class="p-4 bg-yellow-50 rounded-lg">
                                <h4 class="text-md font-medium text-yellow-900 mb-3">Consulta por Rango de Fechas</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="date_from" class="block text-sm font-medium text-gray-700">Fecha Desde</label>
                                        <input type="date" name="date_from" id="date_from" 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                               value="{{ old('date_from', now()->subDays(30)->format('Y-m-d')) }}">
                                    </div>
                                    <div>
                                        <label for="date_to" class="block text-sm font-medium text-gray-700">Fecha Hasta</label>
                                        <input type="date" name="date_to" id="date_to" 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                               value="{{ old('date_to', now()->format('Y-m-d')) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Filtros Adicionales --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <label for="status_filter" class="block text-sm font-medium text-gray-700">Estado</label>
                            <select name="status_filter" id="status_filter" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="all">Todos los estados</option>
                                <option value="success" {{ old('status_filter') === 'success' ? 'selected' : '' }}>✅ Exitosos</option>
                                <option value="error" {{ old('status_filter') === 'error' ? 'selected' : '' }}>❌ Con errores</option>
                                <option value="pending" {{ old('status_filter') === 'pending' ? 'selected' : '' }}>⏳ Pendientes</option>
                                <option value="sent" {{ old('status_filter') === 'sent' ? 'selected' : '' }}>📤 Enviados</option>
                            </select>
                        </div>

                        <div>
                            <label for="confirmation_number" class="block text-sm font-medium text-gray-700">Número de Confirmación</label>
                            <input type="text" name="confirmation_number" id="confirmation_number" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Número AFIP/DNA"
                                   value="{{ old('confirmation_number') }}">
                        </div>

                        <div>
                            <label for="limit" class="block text-sm font-medium text-gray-700">Límite de Resultados</label>
                            <select name="limit" id="limit" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="20" {{ old('limit', '20') === '20' ? 'selected' : '' }}>20 resultados</option>
                                <option value="50" {{ old('limit') === '50' ? 'selected' : '' }}>50 resultados</option>
                                <option value="100" {{ old('limit') === '100' ? 'selected' : '' }}>100 resultados</option>
                            </select>
                        </div>
                    </div>

                    {{-- Botones de Acción --}}
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                        <div class="text-sm text-gray-500">
                            <span class="font-medium">Empresa:</span> {{ $company->legal_name }} ({{ $company->tax_id }})
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" onclick="resetForm()" 
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                Limpiar Formulario
                            </button>
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Consultar Estado
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Resultados de Consulta --}}
            @if(session('query_results'))
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <h3 class="text-lg font-medium text-gray-900">Resultados de la Consulta</h3>
                            </div>
                            @if(session('query_summary'))
                                <div class="text-sm text-gray-600">
                                    Total encontrados: <span class="font-semibold">{{ session('query_summary')['total_records'] ?? 0 }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Estadísticas Rápidas --}}
                    @if(session('query_summary'))
                        @php $summary = session('query_summary'); @endphp
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">{{ $summary['success'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">Exitosos</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-red-600">{{ $summary['error'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">Con errores</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-yellow-600">{{ $summary['pending'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">Pendientes</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-gray-600">{{ $summary['total'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">Total</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Tabla de Resultados --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referencia</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Viaje</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Envío</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Confirmación</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach(session('query_results', []) as $result)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $result['external_reference'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                {{ $result['webservice_type'] ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div>{{ $result['voyage_code'] ?? 'N/A' }}</div>
                                            @if(isset($result['barge_name']))
                                                <div class="text-xs text-gray-400">{{ $result['barge_name'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $statusClass = match($result['status'] ?? '') {
                                                    'success' => 'bg-green-100 text-green-800',
                                                    'error' => 'bg-red-100 text-red-800',
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'sent' => 'bg-blue-100 text-blue-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                            @endphp
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusClass }}">
                                                {{ $result['status'] ?? 'unknown' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $result['sent_date'] ? \Carbon\Carbon::parse($result['sent_date'])->format('d/m/Y H:i') : 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $result['confirmation_number'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            @if(isset($result['titulo_id']))
                                                <button type="button" onclick="showDetails('{{ $result['titulo_id'] }}')"
                                                        class="text-indigo-600 hover:text-indigo-900">
                                                    Ver Detalle
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(empty(session('query_results', [])))
                        <div class="px-6 py-12 text-center">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900">No se encontraron resultados</h3>
                            <p class="text-gray-500 mt-2">Intente modificar los criterios de búsqueda</p>
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>

    {{-- JavaScript para funcionalidad dinámica --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar formulario
            toggleQueryFields();
            setupVoyageSelect();
            setupFormValidation();
            setupAutocomplete();
        });

        // Alternar campos según tipo de consulta
        function toggleQueryFields() {
            const queryType = document.querySelector('input[name="query_type"]:checked')?.value;
            const fields = document.querySelectorAll('.query-field');
            
            // Ocultar todos los campos
            fields.forEach(field => field.classList.add('hidden'));
            
            // Mostrar campo específico
            const fieldMap = {
                'by_transaction': 'transaction-fields',
                'by_reference': 'reference-fields', 
                'by_voyage': 'voyage-fields',
                'by_date_range': 'date-fields'
            };
            
            if (fieldMap[queryType]) {
                document.getElementById(fieldMap[queryType])?.classList.remove('hidden');
            }
            
            // Actualizar estilos de radio buttons
            updateRadioStyles();
        }

        // Actualizar estilos de radio buttons
        function updateRadioStyles() {
            document.querySelectorAll('.query-type-radio').forEach(radio => {
                const indicator = radio.parentElement.querySelector('.radio-indicator');
                if (radio.checked) {
                    indicator.classList.add('border-indigo-500', 'bg-indigo-50');
                    indicator.classList.remove('border-gray-300');
                } else {
                    indicator.classList.remove('border-indigo-500', 'bg-indigo-50');
                    indicator.classList.add('border-gray-300');
                }
            });
        }

        // Configurar selector de viajes
        function setupVoyageSelect() {
            const voyageSelect = document.getElementById('voyage_id');
            const voyageCodeInput = document.getElementById('voyage_code');
            
            if (voyageSelect && voyageCodeInput) {
                voyageSelect.addEventListener('change', function() {
                    const selectedOption = this.selectedOptions[0];
                    if (selectedOption && selectedOption.dataset.voyageCode) {
                        voyageCodeInput.value = selectedOption.dataset.voyageCode;
                    }
                });
            }
        }

        // Validación de formulario
        function setupFormValidation() {
            const form = document.getElementById('queryForm');
            
            form.addEventListener('submit', function(e) {
                const queryType = document.querySelector('input[name="query_type"]:checked')?.value;
                
                // Validaciones específicas por tipo
                if (queryType === 'by_transaction') {
                    const transactionId = document.getElementById('transaction_id').value;
                    if (!transactionId.trim()) {
                        e.preventDefault();
                        alert('Debe ingresar un ID de transacción');
                        return;
                    }
                } else if (queryType === 'by_reference') {
                    const reference = document.getElementById('external_reference').value;
                    if (!reference.trim()) {
                        e.preventDefault();
                        alert('Debe ingresar una referencia externa');
                        return;
                    }
                } else if (queryType === 'by_voyage') {
                    const voyageCode = document.getElementById('voyage_code').value;
                    const voyageId = document.getElementById('voyage_id').value;
                    if (!voyageCode.trim() && !voyageId) {
                        e.preventDefault();
                        alert('Debe ingresar un código de viaje o seleccionar un viaje');
                        return;
                    }
                }
                
                // Mostrar indicador de carga
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Consultando...
                `;
            });
        }

        // Limpiar formulario
        function resetForm() {
            document.getElementById('queryForm').reset();
            document.querySelector('input[name="query_type"][value="all"]').checked = true;
            toggleQueryFields();
        }

        // Mostrar detalles de resultado
        function showDetails(tituloId) {
            // TODO: Implementar modal con detalles completos
            alert('Función de detalle en desarrollo. ID: ' + tituloId);
        }

        // Autocompletar avanzado para códigos de viaje con datos PARANA
        function setupAutocomplete() {
            const voyageCodeInput = document.getElementById('voyage_code');
            const confirmationInput = document.getElementById('confirmation_number');
            const externalRefInput = document.getElementById('external_reference');
            
            if (voyageCodeInput) {
                setupVoyageAutocomplete(voyageCodeInput);
            }
            
            if (confirmationInput) {
                setupRecentDataAutocomplete(confirmationInput, 'confirmation_numbers');
            }
            
            if (externalRefInput) {
                setupRecentDataAutocomplete(externalRefInput, 'external_references');
            }
        }

        // Autocompletar para códigos de viaje
        function setupVoyageAutocomplete(input) {
            let timeout;
            
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    const value = this.value.trim();
                    if (value.length >= 2) {
                        fetchParanaData('voyage_codes', value);
                    }
                }, 300);
            });
        }

        // Autocompletar para datos recientes
        function setupRecentDataAutocomplete(input, type) {
            input.addEventListener('focus', function() {
                if (!this.dataset.loaded) {
                    fetchRecentTransactionData(type);
                    this.dataset.loaded = 'true';
                }
            });
        }

        // Obtener datos de PARANA vía AJAX
        function fetchParanaData(type, filter = '') {
            const url = '{{ route("company.webservices.parana-data") }}';
            
            fetch(`${url}?type=${type}&filter=${encodeURIComponent(filter)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateDatalist(type, data.data);
                    }
                })
                .catch(error => {
                    console.error('Error fetching PARANA data:', error);
                });
        }

        // Obtener datos de transacciones recientes
        function fetchRecentTransactionData(type) {
            // Usar datos del servidor pasados a través del $filterData
            @if(isset($filterData['recent_transactions']))
                const recentData = @json($filterData['recent_transactions']);
                const values = recentData.map(transaction => {
                    switch(type) {
                        case 'confirmation_numbers':
                            return transaction.confirmation_number;
                        case 'external_references':
                            return transaction.external_reference;
                        default:
                            return null;
                    }
                }).filter(value => value !== null);
                
                updateDatalist(type, values);
            @endif
        }

        // Actualizar datalist con nuevos datos
        function updateDatalist(type, data) {
            const inputMap = {
                'voyage_codes': 'voyage_code',
                'confirmation_numbers': 'confirmation_number',
                'external_references': 'external_reference'
            };
            
            const inputId = inputMap[type];
            if (!inputId) return;
            
            const input = document.getElementById(inputId);
            if (!input) return;
            
            // Crear o actualizar datalist
            let datalist = document.getElementById(`${inputId}-list`);
            if (!datalist) {
                datalist = document.createElement('datalist');
                datalist.id = `${inputId}-list`;
                input.parentNode.appendChild(datalist);
                input.setAttribute('list', datalist.id);
            }
            
            // Limpiar y agregar nuevas opciones
            datalist.innerHTML = '';
            data.forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                datalist.appendChild(option);
            });
        }

        // Inicializar autocompletar al cargar
        // (Ya se llama en DOMContentLoaded)
    </script>

    {{-- Estilos CSS adicionales --}}
    <style>
        .radio-indicator {
            transition: all 0.2s ease-in-out;
        }
        
        .query-field {
            transition: all 0.3s ease-in-out;
        }
        
        .datalist-container {
            position: relative;
        }
        
        /* Estilos para tabla responsive */
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</x-app-layout>