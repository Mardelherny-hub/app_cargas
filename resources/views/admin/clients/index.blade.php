<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gestión de Clientes
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Listado de clientes registrados en el sistema (Base de datos compartida)
                </p>
            </div>
            <div class="flex space-x-2">
                @if(auth()->user())
                    <a href="{{ route('admin.clients.create') }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nuevo Cliente
                    </a>
                @endif
                <button type="button" 
                        onclick="toggleFilters()" 
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filtros
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Estadísticas rápidas -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <!-- Total de Clientes -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Clientes</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clientes Verificados -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Verificados</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['verified'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pendientes de Verificación -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Pendientes</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['pending'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clientes Inactivos -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Inactivos</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['inactive'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Distribución por Tipo -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Por Tipo</dt>
                                    <dd class="text-sm text-gray-900">
                                        <!-- CORRECCIÓN: Solo mostrar tipos válidos (sin owner) -->
                                        <div class="space-y-1">
                                            <div class="flex justify-between">
                                                <span class="text-xs">Cargadores:</span>
                                                <span class="text-xs font-medium">{{ $stats['by_type']['shipper'] ?? 0 }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-xs">Consignatarios:</span>
                                                <span class="text-xs font-medium">{{ $stats['by_type']['consignee'] ?? 0 }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-xs">Notificatarios:</span>
                                                <span class="text-xs font-medium">{{ $stats['by_type']['notify_party'] ?? 0 }}</span>
                                            </div>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de Filtros (colapsible) -->
            <div id="filters-panel" class="hidden bg-white shadow rounded-lg mb-6">
                <form method="GET" action="{{ route('admin.clients.index') }}" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Búsqueda general -->
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                            <input type="text" 
                                   name="search" 
                                   id="search" 
                                   value="{{ request('search') }}"
                                   placeholder="Nombre, CUIT/RUC, email..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Filtro por país -->
                        <div>
                            <label for="country_id" class="block text-sm font-medium text-gray-700 mb-1">País</label>
                            <select name="country_id" id="country_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Todos los países</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ request('country_id') == $country->id ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Filtro por estado -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Todos los estados</option>
                                @foreach(\App\Models\Client::getStatusOptions() as $key => $label)
                                    <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Filtro por tipo de cliente -->
                        <div>
                            <label for="client_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Cliente</label>
                            <select name="client_type" id="client_type" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Todos los tipos</option>
                                <!-- CORRECCIÓN: Solo tipos válidos (sin owner) -->
                                <option value="shipper" {{ request('client_type') == 'shipper' ? 'selected' : '' }}>
                                    Cargador/Exportador
                                </option>
                                <option value="consignee" {{ request('client_type') == 'consignee' ? 'selected' : '' }}>
                                    Consignatario/Importador
                                </option>
                                <option value="notify_party" {{ request('client_type') == 'notify_party' ? 'selected' : '' }}>
                                    Notificatario
                                </option>
                            </select>
                        </div>

                        <!-- Filtro por verificación -->
                        <div>
                            <label for="verified" class="block text-sm font-medium text-gray-700 mb-1">Verificación</label>
                            <select name="verified" id="verified" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Todos</option>
                                <option value="yes" {{ request('verified') == 'yes' ? 'selected' : '' }}>Solo verificados</option>
                                <option value="no" {{ request('verified') == 'no' ? 'selected' : '' }}>Solo no verificados</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Aplicar Filtros
                            </button>
                            <a href="{{ route('admin.clients.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                Limpiar
                            </a>
                        </div>
                        <div class="text-sm text-gray-500">
                            Mostrando {{ $clients->count() }} de {{ $clients->total() }} clientes
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabla de clientes -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                @if($clients->count() > 0)
                    <div class="min-w-full">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cliente
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Email Principal
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            País
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Verificación
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Empresa Creadora
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($clients as $client)
                                        <tr class="hover:bg-gray-50">
                                            <!-- Información del Cliente -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                            <span class="text-sm font-medium text-gray-700">
                                                                {{ strtoupper(substr($client->legal_name, 0, 2)) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            {{ $client->legal_name }}
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            {{ $client->getFormattedTaxId() }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Email Principal -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $client->getPrimaryEmail() ?? '-' }}
                                                </div>
                                                @if($client->getPrimaryPhone())
                                                    <div class="text-xs text-gray-500">
                                                        {{ $client->getPrimaryPhone() }}
                                                    </div>
                                                @endif
                                            </td>

                                            <!-- País -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span class="text-sm text-gray-900">{{ $client->country->name ?? 'N/A' }}</span>
                                                    @if($client->country)
                                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                            {{ $client->country->iso_code }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>

                                            <!-- Tipo de Cliente -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <!-- CORRECCIÓN: Solo mostrar colores para tipos válidos -->
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    @switch($client->client_type)
                                                        @case('shipper')
                                                            bg-green-100 text-green-800
                                                            @break
                                                        @case('consignee')
                                                            bg-blue-100 text-blue-800
                                                            @break
                                                        @case('notify_party')
                                                            bg-yellow-100 text-yellow-800
                                                            @break
                                                        @default
                                                            bg-gray-100 text-gray-800
                                                    @endswitch">
                                                    {{ \App\Models\Client::CLIENT_TYPES[$client->client_type] ?? $client->client_type }}
                                                </span>
                                            </td>

                                            <!-- Estado -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    @switch($client->status)
                                                        @case('active')
                                                            bg-green-100 text-green-800
                                                            @break
                                                        @case('inactive')
                                                            bg-red-100 text-red-800
                                                            @break
                                                        @case('suspended')
                                                            bg-yellow-100 text-yellow-800
                                                            @break
                                                        @default
                                                            bg-gray-100 text-gray-800
                                                    @endswitch">
                                                    {{ \App\Models\Client::STATUSES[$client->status] ?? $client->status }}
                                                </span>
                                            </td>

                                            <!-- Verificación -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                @if($client->verified_at)
                                                    <div class="flex items-center">
                                                        <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <span class="text-xs text-green-600">
                                                            {{ $client->verified_at->format('d/m/Y') }}
                                                        </span>
                                                    </div>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Pendiente
                                                    </span>
                                                @endif
                                            </td>

                                            <!-- Empresa Creadora -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $client->createdByCompany->commercial_name ?? $client->createdByCompany->legal_name ?? 'Sistema' }}
                                                </div>
                                                @if($client->created_at)
                                                    <div class="text-xs text-gray-500">
                                                        {{ $client->created_at->format('d/m/Y') }}
                                                    </div>
                                                @endif
                                            </td>
                                            
                                            <!-- Acciones -->
                                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-1">
                                                    <!-- Ver Detalles -->
                                                    <a href="{{ route('admin.clients.show', $client) }}" 
                                                       class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                                                       title="Ver detalles">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </a>

                                                    <!-- Editar -->
                                                    @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                                                        <a href="{{ route('admin.clients.edit', $client) }}" 
                                                           class="text-indigo-600 hover:text-indigo-900 p-1 rounded hover:bg-indigo-50"
                                                           title="Editar">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </a>
                                                    @endif

                                                    <!-- Verificar -->
                                                    @if(!$client->verified_at && auth()->user()->hasRole(['super-admin', 'company-admin']))
                                                        <form method="POST" action="{{ route('admin.clients.verify', $client) }}" class="inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" 
                                                                    class="text-green-600 hover:text-green-900 p-1 rounded hover:bg-green-50"
                                                                    title="Verificar cliente"
                                                                    onclick="return confirm('¿Confirma que desea verificar este cliente?')">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    @endif

                                                    <!-- Toggle Estado -->
                                                    @if(auth()->user()->hasRole(['super-admin', 'company-admin']))
                                                        <form method="POST" action="{{ route('admin.clients.toggle-status', $client) }}" class="inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" 
                                                                    class="text-yellow-600 hover:text-yellow-900 p-1 rounded hover:bg-yellow-50"
                                                                    title="{{ $client->status === 'active' ? 'Desactivar' : 'Activar' }} cliente"
                                                                    onclick="return confirm('¿Confirma que desea {{ $client->status === 'active' ? 'desactivar' : 'activar' }} este cliente?')">
                                                                @if($client->status === 'active')
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18 12M6 6l12 12"/>
                                                                    </svg>
                                                                @else
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                    </svg>
                                                                @endif
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        {{ $clients->withQueryString()->links() }}
                    </div>
                @else
                    <!-- Estado vacío -->
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No hay clientes</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            No se encontraron clientes que coincidan con los filtros aplicados.
                        </p>
                        <div class="mt-6">
                            @if(auth()->user())
                                <a href="{{ route('admin.clients.create') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Crear Primer Cliente
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- JavaScript para funcionalidad de filtros -->
    <script>
        function toggleFilters() {
            const panel = document.getElementById('filters-panel');
            panel.classList.toggle('hidden');
        }

        // Auto-submit del formulario de filtros cuando cambian los selects
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('#filters-panel select');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    // Auto-submit solo si hay algún valor seleccionado
                    const form = this.closest('form');
                    const formData = new FormData(form);
                    let hasFilters = false;
                    
                    for (let [key, value] of formData.entries()) {
                        if (value && value !== '') {
                            hasFilters = true;
                            break;
                        }
                    }
                    
                    if (hasFilters) {
                        form.submit();
                    }
                });
            });
        });
    </script>
</x-app-layout>