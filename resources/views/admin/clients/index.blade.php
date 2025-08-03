<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Gestión de Clientes') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Gestionar empresas propietarias de mercadería
                </p>
            </div>
            <div class="flex space-x-3">
                <button id="toggle-filters" 
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    Filtros
                </button>
                <a href="{{ route('admin.clients.create') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Nuevo Cliente
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estadísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Total Clientes
                                    </dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            {{ number_format($stats['total'] ?? 0) }}
                                        </div>
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
                                <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Verificados
                                    </dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            {{ number_format($stats['verified'] ?? 0) }}
                                        </div>
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
                                <svg class="h-6 w-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.314 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Pendientes
                                    </dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            {{ number_format($stats['pending'] ?? 0) }}
                                        </div>
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
                                <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Inactivos
                                    </dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900">
                                            {{ number_format($stats['inactive'] ?? 0) }}
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de filtros (colapsable) -->
            <div id="filters-panel" class="hidden bg-white shadow rounded-lg">
                <div class="px-6 py-4">
                    <form method="GET" action="{{ route('admin.clients.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Búsqueda -->
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                                <input type="text" 
                                       name="search" 
                                       id="search"
                                       value="{{ request('search') }}" 
                                       placeholder="CUIT/RUC, razón social, nombre comercial..."
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>

                            <!-- País -->
                            <div>
                                <label for="country_id" class="block text-sm font-medium text-gray-700">País</label>
                                <select name="country_id" 
                                        id="country_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Todos los países</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ request('country_id') == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Estado -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select name="status" 
                                        id="status"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Todos los estados</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activos</option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivos</option>
                                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspendidos</option>
                                </select>
                            </div>

                            <!-- Verificación -->
                            <div>
                                <label for="verified" class="block text-sm font-medium text-gray-700">Verificación</label>
                                <select name="verified" 
                                        id="verified"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="yes" {{ request('verified') === 'yes' ? 'selected' : '' }}>Verificados</option>
                                    <option value="no" {{ request('verified') === 'no' ? 'selected' : '' }}>Sin verificar</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('admin.clients.index') }}" 
                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Limpiar
                            </a>
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de clientes -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Lista de Clientes
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                {{ $clients->total() }} cliente{{ $clients->total() !== 1 ? 's' : '' }} encontrado{{ $clients->total() !== 1 ? 's' : '' }}
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <!-- Botón de importación CSV -->
                            <button type="button" 
                                    onclick="document.getElementById('csv-import-modal').classList.remove('hidden')"
                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                Importar CSV
                            </button>
                            <!-- Ordenamiento -->
                            <div class="relative">
                                <select onchange="window.location.href=this.value" 
                                        class="appearance-none bg-white border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'updated_at', 'order' => 'desc']) }}"
                                            {{ request('sort') === 'updated_at' && request('order') === 'desc' ? 'selected' : '' }}>
                                        Actualización (recientes)
                                    </option>
                                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'updated_at', 'order' => 'asc']) }}"
                                            {{ request('sort') === 'updated_at' && request('order') === 'asc' ? 'selected' : '' }}>
                                        Actualización (antiguos)
                                    </option>
                                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'legal_name', 'order' => 'asc']) }}"
                                            {{ request('sort') === 'legal_name' && request('order') === 'asc' ? 'selected' : '' }}>
                                        Nombre (A-Z)
                                    </option>
                                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'legal_name', 'order' => 'desc']) }}"
                                            {{ request('sort') === 'legal_name' && request('order') === 'desc' ? 'selected' : '' }}>
                                        Nombre (Z-A)
                                    </option>
                                    <option value="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'order' => 'desc']) }}"
                                            {{ request('sort') === 'created_at' && request('order') === 'desc' ? 'selected' : '' }}>
                                        Creación (recientes)
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                @if($clients->count() > 0)
                    <ul class="divide-y divide-gray-200">
                        @foreach($clients as $client)
                            <li class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center min-w-0 flex-1">
                                        <!-- Información principal -->
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center space-x-3">
                                                <div class="flex-shrink-0">
                                                    <!-- Indicador de estado -->
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 
                                                           ($client->status === 'inactive' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                                        {{ ucfirst($client->status) }}
                                                    </span>
                                                    @if($client->verified_at)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 ml-2">
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                            </svg>
                                                            Verificado
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <div class="flex items-center space-x-2">
                                                    <p class="text-sm font-medium text-gray-900 truncate">
                                                        {{ $client->legal_name }}
                                                    </p>
                                                    @if($client->commercial_name && $client->commercial_name !== $client->legal_name)
                                                        <span class="text-gray-500 text-sm">
                                                            ({{ $client->commercial_name }})
                                                        </span>
                                                    @endif
                                                </div>
                                                
                                                <div class="mt-1 flex items-center space-x-4 text-sm text-gray-500">
                                                    @if($client->tax_id)
                                                        <span class="flex items-center">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                            </svg>
                                                            {{ $client->getFormattedTaxId() }}
                                                        </span>
                                                    @endif
                                                    
                                                    @if($client->country)
                                                        <span class="flex items-center">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            </svg>
                                                            {{ $client->country->name }}
                                                        </span>
                                                    @endif

                                                    @if($client->primaryContact && $client->primaryContact->email)
                                                        <span class="flex items-center">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                            </svg>
                                                            {{ $client->primaryContact->email }}
                                                        </span>
                                                    @endif

                                                    @if($client->createdByCompany)
                                                        <span class="flex items-center">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                            </svg>
                                                            {{ $client->createdByCompany->legal_name }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Acciones -->
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('admin.clients.show', $client) }}" 
                                           class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                            Ver
                                        </a>
                                        <a href="{{ route('admin.clients.edit', $client) }}" 
                                           class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                            Editar
                                        </a>
                                        
                                        @if(!$client->verified_at)
                                            <form method="POST" action="{{ route('admin.clients.verify', $client) }}" class="inline-block">
                                                @csrf
                                                <button type="submit" 
                                                        class="text-green-600 hover:text-green-900 text-sm font-medium"
                                                        onclick="return confirm('¿Verificar este cliente?')">
                                                    Verificar
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.clients.toggle-status', $client) }}" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="text-{{ $client->status === 'active' ? 'red' : 'green' }}-600 hover:text-{{ $client->status === 'active' ? 'red' : 'green' }}-900 text-sm font-medium"
                                                    onclick="return confirm('¿Cambiar estado del cliente?')">
                                                {{ $client->status === 'active' ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>

                                        <!-- Dropdown de más acciones -->
                                        <div class="relative inline-block text-left">
                                            <button type="button" 
                                                    class="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                                    onclick="toggleDropdown({{ $client->id }})">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                                </svg>
                                            </button>
                                            <div id="dropdown-{{ $client->id }}" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                                <div class="py-1">
                                                    <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" class="block">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-gray-100"
                                                                onclick="return confirm('¿Estás seguro de eliminar este cliente? Esta acción no se puede deshacer.')">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <!-- Paginación -->
                    <div class="px-4 py-3 border-t border-gray-200 sm:px-6">
                        {{ $clients->links() }}
                    </div>
                @else
                    <div class="px-4 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Sin clientes</h3>
                        <p class="mt-1 text-sm text-gray-500">No se encontraron clientes con los filtros aplicados.</p>
                        <div class="mt-6">
                            <a href="{{ route('admin.clients.create') }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Crear primer cliente
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modal de importación CSV -->
    <div id="csv-import-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Importar Clientes desde CSV</h3>
                    <button type="button" 
                            onclick="document.getElementById('csv-import-modal').classList.add('hidden')"
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="{{ route('admin.clients.bulk-import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label for="import_file" class="block text-sm font-medium text-gray-700 mb-2">
                            Archivo CSV <span class="text-red-500">*</span>
                        </label>
                        <input type="file" 
                               name="import_file" 
                               id="import_file" 
                               accept=".csv,.xlsx,.xls" 
                               required
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="mt-1 text-xs text-gray-500">
                            Formatos soportados: CSV, Excel (.xlsx, .xls). Máximo 10MB.
                        </p>
                    </div>

                    <div class="mb-4">
                        <label for="import_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo de Importación
                        </label>
                        <select name="import_type" id="import_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="clients">Importar solo clientes</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3 flex-1 md:flex md:justify-between">
                                    <p class="text-sm text-blue-700">
                                        El sistema detectará automáticamente el formato de manifiestos PARANA o GUARAN y extraerá la información de clientes correspondiente.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" 
                                onclick="document.getElementById('csv-import-modal').classList.add('hidden')"
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md text-sm font-medium">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Toggle filtros
        document.getElementById('toggle-filters').addEventListener('click', function() {
            const panel = document.getElementById('filters-panel');
            panel.classList.toggle('hidden');
        });

        // Toggle dropdown menus
        function toggleDropdown(clientId) {
            const dropdown = document.getElementById('dropdown-' + clientId);
            const allDropdowns = document.querySelectorAll('[id^="dropdown-"]');
            
            // Cerrar todos los otros dropdowns
            allDropdowns.forEach(function(d) {
                if (d.id !== 'dropdown-' + clientId) {
                    d.classList.add('hidden');
                }
            });
            
            // Toggle el dropdown actual
            dropdown.classList.toggle('hidden');
        }

        // Cerrar dropdowns al hacer click fuera
        document.addEventListener('click', function(event) {
            if (!event.target.matched('button')) {
                const allDropdowns = document.querySelectorAll('[id^="dropdown-"]');
                allDropdowns.forEach(function(d) {
                    d.classList.add('hidden');
                });
            }
        });

        // Cerrar modal al hacer click fuera
        document.getElementById('csv-import-modal').addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
    @endpush
</x-app-layout>