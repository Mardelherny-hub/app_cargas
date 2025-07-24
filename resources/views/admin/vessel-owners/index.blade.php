<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Propietarios de Embarcaciones') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    
    <!-- Header con título y acciones -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Propietarios de Embarcaciones</h1>
                <p class="mt-1 text-sm text-gray-600">Gestión completa de propietarios del sistema</p>
            </div>
            <div class="mt-4 sm:mt-0 flex space-x-2">
                <button onclick="toggleFilters()" 
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    Filtros
                </button>
                <a href="{{ route('admin.vessel-owners.create') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Nuevo Propietario
                </a>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-4">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    {{ number_format($stats['total'] ?? 0) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Activos</dt>
                                <dd class="text-lg font-medium text-green-600">
                                    {{ number_format($stats['active'] ?? 0) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Inactivos</dt>
                                <dd class="text-lg font-medium text-gray-600">
                                    {{ number_format($stats['inactive'] ?? 0) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Suspendidos</dt>
                                <dd class="text-lg font-medium text-red-600">
                                    {{ number_format($stats['suspended'] ?? 0) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Verificados</dt>
                                <dd class="text-lg font-medium text-indigo-600">
                                    {{ number_format($stats['verified'] ?? 0) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a1 1 0 102 0V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 2a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm0 3a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Operadores</dt>
                                <dd class="text-lg font-medium text-purple-600">
                                    {{ number_format($stats['operators'] ?? 0) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-teal-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Representantes</dt>
                                <dd class="text-lg font-medium text-teal-600">
                                    {{ number_format($stats['representatives'] ?? 0) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel de filtros (colapsable) -->
    <div id="filters-panel" class="hidden bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4">
            <form method="GET" action="{{ route('admin.vessel-owners.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Búsqueda -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                        <input type="text" 
                               name="search" 
                               id="search"
                               value="{{ request('search') }}" 
                               placeholder="CUIT/RUC, razón social..."
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <!-- Estado -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                        <select name="status" 
                                id="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Todos los estados</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activo</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                            <option value="pending_verification" {{ request('status') === 'pending_verification' ? 'selected' : '' }}>Pendiente Verificación</option>
                        </select>
                    </div>

                    <!-- Tipo Transportista -->
                    <div>
                        <label for="transportista_type" class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select name="transportista_type" 
                                id="transportista_type"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Todos los tipos</option>
                            <option value="O" {{ request('transportista_type') === 'O' ? 'selected' : '' }}>Operador</option>
                            <option value="R" {{ request('transportista_type') === 'R' ? 'selected' : '' }}>Representante</option>
                        </select>
                    </div>

                    <!-- País -->
                    <div>
                        <label for="country_id" class="block text-sm font-medium text-gray-700">País</label>
                        <select name="country_id" 
                                id="country_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Todos los países</option>
                            @foreach($countries as $id => $name)
                                <option value="{{ $id }}" {{ request('country_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Empresa -->
                    <div>
                        <label for="company_id" class="block text-sm font-medium text-gray-700">Empresa</label>
                        <select name="company_id" 
                                id="company_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Todas las empresas</option>
                            @foreach($companies as $id => $name)
                                <option value="{{ $id }}" {{ request('company_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Verificación fiscal -->
                    <div>
                        <label for="verified" class="block text-sm font-medium text-gray-700">Verificación Fiscal</label>
                        <select name="verified" 
                                id="verified"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Todos</option>
                            <option value="1" {{ request('verified') === '1' ? 'selected' : '' }}>
                                Solo verificados
                            </option>
                            <option value="0" {{ request('verified') === '0' ? 'selected' : '' }}>
                                Sin verificar
                            </option>
                        </select>
                    </div>

                    <!-- Ordenamiento -->
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-gray-700">Ordenar por</label>
                        <select name="sort_by" 
                                id="sort_by"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="legal_name" {{ request('sort_by') === 'legal_name' ? 'selected' : '' }}>Razón Social</option>
                            <option value="tax_id" {{ request('sort_by') === 'tax_id' ? 'selected' : '' }}>CUIT/RUC</option>
                            <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Fecha Creación</option>
                            <option value="last_activity_at" {{ request('sort_by') === 'last_activity_at' ? 'selected' : '' }}>Última Actividad</option>
                        </select>
                    </div>

                    <!-- Orden -->
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700">Orden</label>
                        <select name="sort_order" 
                                id="sort_order"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="asc" {{ request('sort_order') === 'asc' ? 'selected' : '' }}>Ascendente</option>
                            <option value="desc" {{ request('sort_order') === 'desc' ? 'selected' : '' }}>Descendente</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <div class="flex space-x-2">
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Aplicar Filtros
                        </button>
                        <a href="{{ route('admin.vessel-owners.index') }}" 
                           class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                            Limpiar
                        </a>
                    </div>
                    <div class="text-sm text-gray-500">
                        Mostrando {{ $vesselOwners->count() }} de {{ $vesselOwners->total() }} resultados
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Acciones en masa -->
    <div id="bulk-actions" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span class="text-sm text-yellow-800" id="selected-count">0 propietarios seleccionados</span>
            </div>
            <div class="flex space-x-2">
                <button onclick="bulkAction('activate')" 
                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                    Activar
                </button>
                <button onclick="bulkAction('deactivate')" 
                        class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
                    Desactivar
                </button>
                <button onclick="bulkAction('suspend')" 
                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                    Suspender
                </button>
                <button onclick="bulkAction('verify')" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                    Verificar
                </button>
                <button onclick="clearSelection()" 
                        class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1 rounded text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla de Propietarios -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <div class="px-4 py-5 sm:p-6">
            @if($vesselOwners->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">
                                    <input type="checkbox" id="select-all" onchange="toggleSelectAll()"
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Propietario
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tipo
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Empresa
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    País
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Embarcaciones
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Verificación
                                </th>
                                <th scope="col" class="relative px-4 py-3">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($vesselOwners as $owner)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <input type="checkbox" value="{{ $owner->id }}" 
                                               class="vessel-owner-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                               onchange="updateSelection()">
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <a href="{{ route('admin.vessel-owners.show', $owner) }}" 
                                                       class="hover:text-blue-600">
                                                        {{ $owner->legal_name }}
                                                    </a>
                                                </div>
                                                @if($owner->commercial_name && $owner->commercial_name !== $owner->legal_name)
                                                    <div class="text-sm text-gray-500">
                                                        {{ $owner->commercial_name }}
                                                    </div>
                                                @endif
                                                <div class="text-sm text-gray-500">
                                                    {{ $owner->tax_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $owner->transportista_type === 'O' 
                                                ? 'bg-purple-100 text-purple-800' 
                                                : 'bg-teal-100 text-teal-800' }}">
                                            {{ $owner->transportista_type === 'O' ? 'Operador' : 'Representante' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $owner->company->legal_name ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $owner->country->name ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $owner->vessels->count() }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @switch($owner->status)
                                                @case('active')
                                                    bg-green-100 text-green-800
                                                    @break
                                                @case('inactive')
                                                    bg-gray-100 text-gray-800
                                                    @break
                                                @case('suspended')
                                                    bg-red-100 text-red-800
                                                    @break
                                                @case('pending_verification')
                                                    bg-yellow-100 text-yellow-800
                                                    @break
                                                @default
                                                    bg-gray-100 text-gray-800
                                            @endswitch">
                                            @switch($owner->status)
                                                @case('active') Activo @break
                                                @case('inactive') Inactivo @break
                                                @case('suspended') Suspendido @break
                                                @case('pending_verification') Pendiente @break
                                                @default {{ ucfirst($owner->status) }}
                                            @endswitch
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if($owner->tax_id_verified_at)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                Verificado
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                </svg>
                                                Pendiente
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="{{ route('admin.vessel-owners.show', $owner) }}" 
                                               class="text-blue-600 hover:text-blue-900" title="Ver">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </a>
                                            
                                            <a href="{{ route('admin.vessel-owners.edit', $owner) }}" 
                                               class="text-indigo-600 hover:text-indigo-900" title="Editar">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>

                                            @if(!$owner->tax_id_verified_at)
                                                <form method="POST" action="{{ route('admin.vessel-owners.verify', $owner) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" 
                                                            class="text-green-600 hover:text-green-900" 
                                                            title="Verificar"
                                                            onclick="return confirm('¿Confirmar verificación fiscal?')">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif

                                            <form method="POST" action="{{ route('admin.vessel-owners.toggle-status', $owner) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" 
                                                        class="{{ $owner->status === 'active' ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}" 
                                                        title="{{ $owner->status === 'active' ? 'Desactivar' : 'Activar' }}"
                                                        onclick="return confirm('¿Cambiar estado?')">
                                                    @if($owner->status === 'active')
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"></path>
                                                        </svg>
                                                    @else
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                    @endif
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <div class="mt-4">
                    {{ $vesselOwners->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M34 40h10v-4a6 6 0 00-10.712-3.714M34 40H14m20 0v-4a9.971 9.971 0 00-.712-3.714M14 40H4v-4a6 6 0 0110.713-3.714M14 40v-4c0-1.313.253-2.566.713-3.714m0 0A10.003 10.003 0 0124 26c4.21 0 7.813 2.602 9.288 6.286M30 14a6 6 0 11-12 0 6 6 0 0112 0zm12 6a4 4 0 11-8 0 4 4 0 018 0zm-28 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay propietarios</h3>
                    <p class="mt-1 text-sm text-gray-500">Comienza creando el primer propietario de embarcaciones.</p>
                    <div class="mt-6">
                        <a href="{{ route('admin.vessel-owners.create') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Nuevo Propietario
                        </a>
                    </div>
                </div>
            @endif
            </div>
            </div>
        </div>
    </div>

<!-- JavaScript para funcionalidad interactiva -->
<script>
function toggleFilters() {
    const panel = document.getElementById('filters-panel');
    panel.classList.toggle('hidden');
}

function toggleSelectAll() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.vessel-owner-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelection();
}

function updateSelection() {
    const checkboxes = document.querySelectorAll('.vessel-owner-checkbox:checked');
    const bulkActions = document.getElementById('bulk-actions');
    const selectedCount = document.getElementById('selected-count');
    
    if (checkboxes.length > 0) {
        bulkActions.classList.remove('hidden');
        selectedCount.textContent = `${checkboxes.length} propietarios seleccionados`;
    } else {
        bulkActions.classList.add('hidden');
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.vessel-owner-checkbox');
    const selectAll = document.getElementById('select-all');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    selectAll.checked = false;
    
    updateSelection();
}

function bulkAction(action) {
    const checkboxes = document.querySelectorAll('.vessel-owner-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('Selecciona al menos un propietario');
        return;
    }
    
    const actions = {
        'activate': 'activar',
        'deactivate': 'desactivar', 
        'suspend': 'suspender',
        'verify': 'verificar'
    };
    
    if (confirm(`¿Confirmar ${actions[action]} ${ids.length} propietarios?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("admin.vessel-owners.bulk-action") }}';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);
        
        ids.forEach(id => {
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'vessel_owner_ids[]';
            idInput.value = id;
            form.appendChild(idInput);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
        </div>
    </div>
</x-app-layout>