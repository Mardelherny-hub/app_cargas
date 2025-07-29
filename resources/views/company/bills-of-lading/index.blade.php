<x-app-layout>
    @php
        $user = Auth::user();
        $company = null;
        $companyRoles = [];
        
        if ($user) {
            // Obtener información de la empresa
            if ($user->userable_type === 'App\\Models\\Company') {
                $company = $user->userable;
                $companyRoles = $company->company_roles ?? [];
            } elseif ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
                $company = $user->userable->company;
                $companyRoles = $company->company_roles ?? [];
            }
        }
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Conocimientos de Embarque
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    @if(in_array('Cargas', $companyRoles))
                        Gestión completa de conocimientos para manifiestos aduaneros
                    @elseif(in_array('Desconsolidador', $companyRoles))
                        Visualización de conocimientos y gestión de títulos hijo
                    @elseif(in_array('Transbordos', $companyRoles))
                        Visualización de conocimientos para transbordos
                    @else
                        Visualización de sus conocimientos de embarque
                    @endif
                </p>
            </div>
            
            {{-- Botón crear solo para rol "Cargas" --}}
            @if(in_array('Cargas', $companyRoles))
            <div>
                <a href="{{ route('company.bills-of-lading.create') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Nuevo Conocimiento
                </a>
            </div>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Estadísticas rápidas --}}
            @if(count($companyRoles) > 0)
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-blue-600 text-white rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold">{{ $stats['total'] ?? 0 }}</div>
                    <div class="text-sm">Total</div>
                </div>
                <div class="bg-yellow-500 text-white rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold">{{ $stats['draft'] ?? 0 }}</div>
                    <div class="text-sm">Borradores</div>
                </div>
                <div class="bg-green-600 text-white rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold">{{ $stats['verified'] ?? 0 }}</div>
                    <div class="text-sm">Verificados</div>
                </div>
                <div class="bg-blue-500 text-white rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold">{{ $stats['consolidated'] ?? 0 }}</div>
                    <div class="text-sm">Consolidados</div>
                </div>
                <div class="bg-red-600 text-white rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold">{{ $stats['dangerous_goods'] ?? 0 }}</div>
                    <div class="text-sm">Mercadería Peligrosa</div>
                </div>
                <div class="bg-gray-600 text-white rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold">{{ $stats['sent_to_argentina'] ?? 0 }}</div>
                    <div class="text-sm">Enviados AR</div>
                </div>
            </div>
            @endif

            {{-- Filtros --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                        </svg>
                        Filtros de Búsqueda
                    </h3>
                </div>
                <div class="p-6">
                    <form method="GET" action="{{ route('company.bills-of-lading.index') }}">
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                            {{-- Búsqueda por texto --}}
                            <div class="lg:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700">Búsqueda General</label>
                                <input type="text" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                       id="search" 
                                       name="search" 
                                       value="{{ request('search') }}"
                                       placeholder="Número BL, cargador, consignatario...">
                            </div>

                            {{-- Estado --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" id="status" name="status">
                                    <option value="">Todos los estados</option>
                                    @foreach($filterData['statuses'] as $value => $label)
                                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Puerto de Carga --}}
                            <div>
                                <label for="loading_port_id" class="block text-sm font-medium text-gray-700">Puerto Carga</label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" id="loading_port_id" name="loading_port_id">
                                    <option value="">Todos</option>
                                    @foreach($filterData['loadingPorts'] as $port)
                                        <option value="{{ $port->id }}" {{ request('loading_port_id') == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Puerto de Descarga --}}
                            <div>
                                <label for="discharge_port_id" class="block text-sm font-medium text-gray-700">Puerto Descarga</label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" id="discharge_port_id" name="discharge_port_id">
                                    <option value="">Todos</option>
                                    @foreach($filterData['dischargePorts'] as $port)
                                        <option value="{{ $port->id }}" {{ request('discharge_port_id') == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mt-4">
                            {{-- Cargador --}}
                            <div>
                                <label for="shipper_id" class="block text-sm font-medium text-gray-700">Cargador/Exportador</label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" id="shipper_id" name="shipper_id">
                                    <option value="">Todos</option>
                                    @foreach($filterData['shippers'] as $shipper)
                                        <option value="{{ $shipper->id }}" {{ request('shipper_id') == $shipper->id ? 'selected' : '' }}>
                                            {{ $shipper->legal_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Consignatario --}}
                            <div>
                                <label for="consignee_id" class="block text-sm font-medium text-gray-700">Consignatario</label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" id="consignee_id" name="consignee_id">
                                    <option value="">Todos</option>
                                    @foreach($filterData['consignees'] as $consignee)
                                        <option value="{{ $consignee->id }}" {{ request('consignee_id') == $consignee->id ? 'selected' : '' }}>
                                            {{ $consignee->legal_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Tipo de Conocimiento --}}
                            <div>
                                <label for="bill_type" class="block text-sm font-medium text-gray-700">Tipo</label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" id="bill_type" name="bill_type">
                                    <option value="">Todos los tipos</option>
                                    <option value="original" {{ request('bill_type') === 'original' ? 'selected' : '' }}>Original</option>
                                    <option value="copy" {{ request('bill_type') === 'copy' ? 'selected' : '' }}>Copia</option>
                                    <option value="duplicate" {{ request('bill_type') === 'duplicate' ? 'selected' : '' }}>Duplicado</option>
                                    <option value="amendment" {{ request('bill_type') === 'amendment' ? 'selected' : '' }}>Enmienda</option>
                                </select>
                            </div>

                            {{-- Fecha desde --}}
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">Fecha Desde</label>
                                <input type="date" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                       id="date_from" 
                                       name="date_from" 
                                       value="{{ request('date_from') }}">
                            </div>

                            {{-- Fecha hasta --}}
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">Fecha Hasta</label>
                                <input type="date" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                                       id="date_to" 
                                       name="date_to" 
                                       value="{{ request('date_to') }}">
                            </div>
                        </div>

                        {{-- Webservices Status (solo para rol Cargas) --}}
                        @if(in_array('Cargas', $companyRoles))
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Estado Webservices</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <select class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" name="argentina_status">
                                        <option value="">Argentina - Todos</option>
                                        <option value="pending" {{ request('argentina_status') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                                        <option value="sent" {{ request('argentina_status') === 'sent' ? 'selected' : '' }}>Enviado</option>
                                        <option value="confirmed" {{ request('argentina_status') === 'confirmed' ? 'selected' : '' }}>Confirmado</option>
                                        <option value="error" {{ request('argentina_status') === 'error' ? 'selected' : '' }}>Error</option>
                                    </select>
                                </div>
                                <div>
                                    <select class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" name="paraguay_status">
                                        <option value="">Paraguay - Todos</option>
                                        <option value="pending" {{ request('paraguay_status') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                                        <option value="sent" {{ request('paraguay_status') === 'sent' ? 'selected' : '' }}>Enviado</option>
                                        <option value="confirmed" {{ request('paraguay_status') === 'confirmed' ? 'selected' : '' }}>Confirmado</option>
                                        <option value="error" {{ request('paraguay_status') === 'error' ? 'selected' : '' }}>Error</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="flex items-center justify-end mt-6 space-x-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Filtrar
                            </button>
                            <a href="{{ route('company.bills-of-lading.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tabla de resultados --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="flex items-center justify-between p-6 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        Resultados ({{ $billsOfLading->total() }} registros)
                        @if($user->hasRole('user') && $user->userable_type === 'App\\Models\\Operator' && !in_array('Cargas', $companyRoles))
                            <span class="text-sm text-gray-500 font-normal">- Solo sus conocimientos</span>
                        @endif
                    </h3>
                    @if(in_array('Cargas', $companyRoles))
                    <div class="flex space-x-2">
                        <button type="button" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                            <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Excel
                        </button>
                        <button type="button" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                            <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            PDF
                        </button>
                    </div>
                    @endif
                </div>
                
                <div class="overflow-x-auto">
                    @if($billsOfLading->count() > 0)
                        <table class="w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'bill_number', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center hover:text-gray-700">
                                            Nº Conocimiento
                                            @if(request('sort') === 'bill_number')
                                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ request('direction') === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}"/>
                                                </svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Envío</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cargador</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consignatario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ruta</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Carga</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Características</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($billsOfLading as $bill)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <a href="{{ route('company.bills-of-lading.show', $bill) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                                    {{ $bill->bill_number }}
                                                </a>
                                                @if($bill->is_master_bill)
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        Maestro
                                                    </span>
                                                @elseif($bill->is_house_bill)
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                        Hijo
                                                    </span>
                                                @endif
                                            </div>
                                            @if($bill->bill_date)
                                                <div class="text-sm text-gray-500">{{ $bill->bill_date->format('d/m/Y') }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $bill->shipment->shipment_number }}</div>
                                            <div class="text-sm text-gray-500">{{ $bill->shipment->voyage->voyage_number }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ Str::limit($bill->shipper->legal_name, 25) }}</div>
                                            <div class="text-sm text-gray-500">{{ $bill->shipper->tax_id }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ Str::limit($bill->consignee->legal_name, 25) }}</div>
                                            <div class="text-sm text-gray-500">{{ $bill->consignee->tax_id }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <div><strong>De:</strong> {{ $bill->loadingPort->name }}</div>
                                                <div><strong>A:</strong> {{ $bill->dischargePort->name }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><strong>{{ number_format($bill->total_packages) }}</strong> bultos</div>
                                            <div class="text-sm text-gray-500"><strong>{{ number_format($bill->gross_weight_kg, 2) }}</strong> kg</div>
                                            @if($bill->volume_m3)
                                                <div class="text-sm text-gray-500">{{ number_format($bill->volume_m3, 2) }} m³</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $statusColors = [
                                                    'draft' => 'bg-gray-100 text-gray-800',
                                                    'confirmed' => 'bg-blue-100 text-blue-800',
                                                    'loaded' => 'bg-indigo-100 text-indigo-800',
                                                    'in_transit' => 'bg-yellow-100 text-yellow-800',
                                                    'discharged' => 'bg-green-100 text-green-800',
                                                    'delivered' => 'bg-green-100 text-green-800',
                                                    'cancelled' => 'bg-red-100 text-red-800'
                                                ];
                                                $statusLabels = [
                                                    'draft' => 'Borrador',
                                                    'confirmed' => 'Confirmado',
                                                    'loaded' => 'Cargado',
                                                    'in_transit' => 'En Tránsito',
                                                    'discharged' => 'Descargado',
                                                    'delivered' => 'Entregado',
                                                    'cancelled' => 'Cancelado'
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$bill->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ $statusLabels[$bill->status] ?? ucfirst($bill->status) }}
                                            </span>
                                            @if($bill->verified_at)
                                                <div class="mt-1">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Verificado
                                                    </span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                @if($bill->contains_dangerous_goods)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800" title="Mercadería Peligrosa">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                @endif
                                                @if($bill->requires_refrigeration)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800" title="Refrigerado">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 2L3 7v11a2 2 0 002 2h10a2 2 0 002-2V7l-7-5z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                @endif
                                                @if($bill->is_consolidated)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800" title="Consolidado">
                                                        C
                                                    </span>
                                                @endif
                                                @if($bill->argentina_sent_at)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800" title="Enviado a Argentina">AR</span>
                                                @endif
                                                @if($bill->paraguay_sent_at)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800" title="Enviado a Paraguay">PY</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex justify-center space-x-1">
                                                {{-- Ver siempre disponible --}}
                                                <a href="{{ route('company.bills-of-lading.show', $bill) }}" 
                                                   class="text-blue-600 hover:text-blue-800 p-1" 
                                                   title="Ver detalles">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </a>

                                                {{-- Editar solo para rol "Cargas" --}}
                                                @if(in_array('Cargas', $companyRoles))
                                                    <a href="{{ route('company.bills-of-lading.edit', $bill) }}" 
                                                       class="text-gray-600 hover:text-gray-800 p-1" 
                                                       title="Editar">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </a>
                                                @endif

                                                {{-- PDF siempre disponible --}}
                                                <a href="{{ route('company.bills-of-lading.pdf', $bill) }}" 
                                                   class="text-red-600 hover:text-red-800 p-1" 
                                                   title="Generar PDF" 
                                                   target="_blank">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                </a>

                                                {{-- Eliminar solo para rol "Cargas" y si puede ser eliminado --}}
                                                @if(in_array('Cargas', $companyRoles) && !$bill->webservice_sent_at)
                                                    <button type="button" 
                                                            class="text-red-600 hover:text-red-800 p-1" 
                                                            title="Eliminar"
                                                            onclick="confirmDelete({{ $bill->id }}, '{{ $bill->bill_number }}')">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Paginación --}}
                        <div class="flex items-center justify-between p-6 bg-gray-50 border-t border-gray-200">
                            <div class="text-sm text-gray-700">
                                Mostrando {{ $billsOfLading->firstItem() }} a {{ $billsOfLading->lastItem() }} 
                                de {{ $billsOfLading->total() }} resultados
                            </div>
                            {{ $billsOfLading->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No se encontraron conocimientos de embarque</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                @if(request()->hasAny(['search', 'status', 'shipment_id', 'shipper_id', 'consignee_id']))
                                    Intenta modificar los filtros de búsqueda.
                                @else
                                    Aún no hay conocimientos de embarque registrados.
                                @endif
                            </p>
                            @if(in_array('Cargas', $companyRoles))
                                <div class="mt-6">
                                    <a href="{{ route('company.bills-of-lading.create') }}" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Crear Primer Conocimiento
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de confirmación para eliminar - Solo para rol "Cargas" --}}
    @if(in_array('Cargas', $companyRoles))
    <div x-data="{ open: false }" x-show="open" class="fixed inset-0 overflow-y-auto" style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Confirmar Eliminación</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    ¿Está seguro de que desea eliminar el conocimiento de embarque <strong id="deleteItemName"></strong>?
                                </p>
                                <p class="text-sm text-red-600 mt-1">Esta acción no se puede deshacer.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <form id="deleteForm" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Eliminar
                        </button>
                    </form>
                    <button type="button" @click="open = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @push('scripts')
    <script>
    @if(in_array('Cargas', $companyRoles))
    function confirmDelete(billId, billNumber) {
        document.getElementById('deleteItemName').textContent = billNumber;
        document.getElementById('deleteForm').action = `/company/bills-of-lading/${billId}`;
        
        // Usar Alpine.js para mostrar el modal
        this.$dispatch('open-modal');
    }
    @endif

    // Auto-submit form cuando cambian ciertos filtros
    document.addEventListener('DOMContentLoaded', function() {
        const autoSubmitFields = ['status', 'loading_port_id', 'discharge_port_id', 'shipper_id', 'consignee_id'];
        
        autoSubmitFields.forEach(function(fieldName) {
            const field = document.getElementById(fieldName);
            if (field) {
                field.addEventListener('change', function() {
                    this.form.submit();
                });
            }
        });
    });
    </script>
    @endpush
</x-app-layout>