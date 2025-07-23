<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tipos de Embarcación') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Header con título y acciones -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Tipos de Embarcación</h1>
                        <p class="mt-1 text-sm text-gray-600">Gestión de tipos de embarcación del sistema</p>
                    </div>
                    <div class="mt-4 sm:mt-0 flex space-x-2">
                        <button onclick="toggleFilters()" 
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            Filtros
                        </button>
                        <button onclick="showImportModal()" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                            </svg>
                            Importar CSV
                        </button>
                        <a href="{{ route('admin.vessel-types.create') }}" 
                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Nuevo Tipo
                        </a>
                    </div>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-7 gap-4">
                    <!-- Total -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
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

                    <!-- Activos -->
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
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ number_format($stats['active'] ?? 0) }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Comunes -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Comunes</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ number_format($stats['common'] ?? 0) }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Barcazas -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a3 3 0 01-3-3V6z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Barcazas</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ number_format($stats['by_category']['barge'] ?? 0) }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remolcadores -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M5.5 16a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 16h-8z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Remolcadores</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ number_format($stats['by_category']['tugboat'] ?? 0) }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empujadores -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                            <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 1.414L7.414 10l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Empujadores</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ number_format($stats['by_category']['pusher'] ?? 0) }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Autopropulsados -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Autoprop.</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            {{ number_format($stats['by_category']['self_propelled'] ?? 0) }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de Filtros -->
            <div id="filters-panel" class="hidden mb-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <form method="GET" action="{{ route('admin.vessel-types.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Búsqueda -->
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Búsqueda</label>
                                <input type="text" name="search" id="search" value="{{ request('search') }}" 
                                       placeholder="Código, nombre o descripción..."
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Categoría -->
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Categoría</label>
                                <select name="category" id="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todas las categorías</option>
                                    @foreach($filterData['categories'] as $key => $value)
                                        <option value="{{ $key }}" {{ request('category') == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Propulsión -->
                            <div>
                                <label for="propulsion_type" class="block text-sm font-medium text-gray-700">Propulsión</label>
                                <select name="propulsion_type" id="propulsion_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todos los tipos</option>
                                    @foreach($filterData['propulsion_types'] as $key => $value)
                                        <option value="{{ $key }}" {{ request('propulsion_type') == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Estado -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todos los estados</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Activos</option>
                                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactivos</option>
                                </select>
                            </div>

                            <!-- Capacidad de Carga -->
                            <div>
                                <label for="cargo_capability" class="block text-sm font-medium text-gray-700">Capacidad de Carga</label>
                                <select name="cargo_capability" id="cargo_capability" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todas las capacidades</option>
                                    @foreach($filterData['cargo_capabilities'] as $key => $value)
                                        <option value="{{ $key }}" {{ request('cargo_capability') == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Navegación -->
                            <div>
                                <label for="navigation_type" class="block text-sm font-medium text-gray-700">Navegación</label>
                                <select name="navigation_type" id="navigation_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todos los tipos</option>
                                    <option value="river" {{ request('navigation_type') == 'river' ? 'selected' : '' }}>Fluvial</option>
                                    <option value="maritime" {{ request('navigation_type') == 'maritime' ? 'selected' : '' }}>Marítima</option>
                                    <option value="coastal" {{ request('navigation_type') == 'coastal' ? 'selected' : '' }}>Costera</option>
                                    <option value="lake" {{ request('navigation_type') == 'lake' ? 'selected' : '' }}>Lacustre</option>
                                </select>
                            </div>

                            <!-- Convoy -->
                            <div>
                                <label for="convoy_capability" class="block text-sm font-medium text-gray-700">Convoy</label>
                                <select name="convoy_capability" id="convoy_capability" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todas las capacidades</option>
                                    <option value="lead" {{ request('convoy_capability') == 'lead' ? 'selected' : '' }}>Puede liderar</option>
                                    <option value="convoy" {{ request('convoy_capability') == 'convoy' ? 'selected' : '' }}>Puede estar en convoy</option>
                                    <option value="push_barges" {{ request('convoy_capability') == 'push_barges' ? 'selected' : '' }}>Puede empujar barcazas</option>
                                    <option value="tow_barges" {{ request('convoy_capability') == 'tow_barges' ? 'selected' : '' }}>Puede remolcar barcazas</option>
                                </select>
                            </div>

                            <!-- Tipo -->
                            <div>
                                <label for="type_filter" class="block text-sm font-medium text-gray-700">Tipo</label>
                                <select name="type_filter" id="type_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todos los tipos</option>
                                    <option value="common" {{ request('type_filter') == 'common' ? 'selected' : '' }}>Comunes</option>
                                    <option value="specialized" {{ request('type_filter') == 'specialized' ? 'selected' : '' }}>Especializados</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <a href="{{ route('admin.vessel-types.index') }}" 
                               class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
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

            <!-- Alertas de Sesión -->
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-green-500 cursor-pointer" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Cerrar</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                    </span>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('warning') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Acciones masivas -->
            <div id="bulk-actions" class="hidden mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span id="selected-count" class="text-sm font-medium text-blue-800"></span>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="bulkAction('activate')" 
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                            Activar
                        </button>
                        <button onclick="bulkAction('deactivate')" 
                                class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-sm">
                            Desactivar
                        </button>
                        <button onclick="bulkAction('mark_common')" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                            Marcar Común
                        </button>
                        <button onclick="bulkAction('mark_specialized')" 
                                class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm">
                            Marcar Especializado
                        </button>
                        <button onclick="bulkAction('delete')" 
                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                            Eliminar
                        </button>
                        <button onclick="clearSelection()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de tipos de embarcación -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" id="select-all" onclick="toggleSelectAll()" 
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc']) }}" 
                                           class="hover:text-gray-700">
                                            Tipo de Embarcación
                                            @if(request('sort_by') === 'name')
                                                <span class="ml-1">{{ request('sort_order') === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'category', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc']) }}" 
                                           class="hover:text-gray-700">
                                            Categoría
                                            @if(request('sort_by') === 'category')
                                                <span class="ml-1">{{ request('sort_order') === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Propulsión
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Capacidades
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Capacidad Carga (Ton)
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Navegación
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Embarcaciones
                                    </th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($vesselTypes as $vesselType)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox" value="{{ $vesselType->id }}" 
                                                   class="vessel-type-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200" 
                                                   onchange="updateSelection()">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8">
                                                    <div class="h-8 w-8 rounded-full flex items-center justify-center" 
                                                         style="background-color: {{ $vesselType->color_code ?? '#6B7280' }}">
                                                        <span class="text-white text-xs font-bold">
                                                            {{ strtoupper(substr($vesselType->category, 0, 1)) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <a href="{{ route('admin.vessel-types.show', $vesselType) }}" 
                                                           class="hover:text-blue-600">
                                                            {{ $vesselType->name }}
                                                        </a>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ $vesselType->code }}
                                                        @if($vesselType->short_name)
                                                            · {{ $vesselType->short_name }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                       @switch($vesselType->category)
                                                           @case('barge') bg-blue-100 text-blue-800 @break
                                                           @case('tugboat') bg-orange-100 text-orange-800 @break
                                                           @case('pusher') bg-red-100 text-red-800 @break
                                                           @case('self_propelled') bg-purple-100 text-purple-800 @break
                                                           @case('mixed') bg-green-100 text-green-800 @break
                                                           @default bg-gray-100 text-gray-800
                                                       @endswitch">
                                                {{ $vesselType->category_name }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $vesselType->propulsion_type_name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-wrap gap-1">
                                                @if($vesselType->handles_containers)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                        Contenedores
                                                    </span>
                                                @endif
                                                @if($vesselType->handles_bulk_cargo)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                        Granel
                                                    </span>
                                                @endif
                                                @if($vesselType->handles_general_cargo)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        General
                                                    </span>
                                                @endif
                                                @if($vesselType->handles_liquid_cargo)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Líquida
                                                    </span>
                                                @endif
                                                @if($vesselType->handles_dangerous_goods)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                        Peligrosa
                                                    </span>
                                                @endif
                                                @if($vesselType->handles_refrigerated_cargo)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-cyan-100 text-cyan-800">
                                                        Refrigerada
                                                    </span>
                                                @endif
                                                @if($vesselType->handles_oversized_cargo)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                        Sobredimensionada
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($vesselType->min_cargo_capacity && $vesselType->max_cargo_capacity)
                                                {{ number_format($vesselType->min_cargo_capacity, 0) }} - {{ number_format($vesselType->max_cargo_capacity, 0) }}
                                            @elseif($vesselType->max_cargo_capacity)
                                                ≤ {{ number_format($vesselType->max_cargo_capacity, 0) }}
                                            @elseif($vesselType->min_cargo_capacity)
                                                ≥ {{ number_format($vesselType->min_cargo_capacity, 0) }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-wrap gap-1">
                                                @if($vesselType->river_navigation)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                        Fluvial
                                                    </span>
                                                @endif
                                                @if($vesselType->maritime_navigation)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                        Marítima
                                                    </span>
                                                @endif
                                                @if($vesselType->coastal_navigation)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Costera
                                                    </span>
                                                @endif
                                                @if($vesselType->lake_navigation)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                        Lacustre
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                           {{ $vesselType->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ $vesselType->active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                                @if($vesselType->is_common)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Común
                                                    </span>
                                                @endif
                                                @if($vesselType->is_specialized)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                        Especializado
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $vesselType->vessels_count ?? 0 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <!-- Toggle Status -->
                                                <form action="{{ route('admin.vessel-types.toggle-status', $vesselType) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" 
                                                            class="text-{{ $vesselType->active ? 'red' : 'green' }}-600 hover:text-{{ $vesselType->active ? 'red' : 'green' }}-900"
                                                            title="{{ $vesselType->active ? 'Desactivar' : 'Activar' }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            @if($vesselType->active)
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            @else
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            @endif
                                                        </svg>
                                                    </button>
                                                </form>

                                                <!-- Duplicate -->
                                                <button onclick="showDuplicateModal({{ $vesselType->id }}, '{{ $vesselType->name }}')" 
                                                        class="text-blue-600 hover:text-blue-900" title="Duplicar">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                    </svg>
                                                </button>

                                                <!-- View -->
                                                <a href="{{ route('admin.vessel-types.show', $vesselType) }}" 
                                                   class="text-gray-600 hover:text-gray-900" title="Ver">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </a>

                                                <!-- Edit -->
                                                <a href="{{ route('admin.vessel-types.edit', $vesselType) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900" title="Editar">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </a>

                                                <!-- Delete -->
                                                @if($vesselType->vessels_count == 0)
                                                    <form action="{{ route('admin.vessel-types.destroy', $vesselType) }}" method="POST" class="inline"
                                                          onsubmit="return confirm('¿Confirmar eliminación del tipo {{ $vesselType->name }}?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Eliminar">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            No se encontraron tipos de embarcación.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    @if($vesselTypes->hasPages())
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="flex-1 flex justify-between sm:hidden">
                                {!! $vesselTypes->previousPageUrl() ? '<a href="'.$vesselTypes->previousPageUrl().'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Anterior</a>' : '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-gray-100">Anterior</span>' !!}
                                {!! $vesselTypes->nextPageUrl() ? '<a href="'.$vesselTypes->nextPageUrl().'" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Siguiente</a>' : '<span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-gray-100">Siguiente</span>' !!}
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Mostrando <span class="font-medium">{{ $vesselTypes->firstItem() }}</span> a <span class="font-medium">{{ $vesselTypes->lastItem() }}</span> de <span class="font-medium">{{ $vesselTypes->total() }}</span> tipos
                                    </p>
                                </div>
                                <div>
                                    {{ $vesselTypes->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Modal para Importar CSV -->
            <div id="import-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Importar Tipos desde CSV</h3>
                        <form action="{{ route('admin.vessel-types.import-csv') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-4">
                                <label for="csv_file" class="block text-sm font-medium text-gray-700">Archivo CSV</label>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv,.txt" required
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <p class="mt-1 text-xs text-gray-500">
                                    <strong>Formato esperado:</strong><br>
                                    code,name,category,propulsion_type,description,min_cargo_capacity,max_cargo_capacity,handles_containers,handles_bulk_cargo,handles_general_cargo,handles_liquid_cargo,handles_dangerous_goods,handles_refrigerated_cargo,handles_oversized_cargo,river_navigation,maritime_navigation,coastal_navigation,lake_navigation,can_be_lead_vessel,can_be_in_convoy,can_push_barges,can_tow_barges,active,is_common,is_specialized
                                </p>
                            </div>
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="skip_header" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <span class="ml-2 text-sm text-gray-700">Omitir primera fila (encabezados)</span>
                                </label>
                            </div>
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="update_existing" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <span class="ml-2 text-sm text-gray-700">Actualizar tipos existentes</span>
                                </label>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideImportModal()" 
                                        class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
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

            <!-- Modal para Duplicar -->
            <div id="duplicate-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Duplicar Tipo de Embarcación</h3>
                        <form id="duplicate-form" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-4">
                                <label for="new_code" class="block text-sm font-medium text-gray-700">Nuevo Código *</label>
                                <input type="text" name="new_code" id="new_code" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label for="new_name" class="block text-sm font-medium text-gray-700">Nuevo Nombre *</label>
                                <input type="text" name="new_name" id="new_name" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="copy_specifications" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <span class="ml-2 text-sm text-gray-700">Copiar especificaciones técnicas</span>
                                </label>
                            </div>
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="copy_capabilities" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <span class="ml-2 text-sm text-gray-700">Copiar capacidades de carga</span>
                                </label>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideDuplicateModal()" 
                                        class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </button>
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Duplicar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
    function toggleFilters() {
        const panel = document.getElementById('filters-panel');
        panel.classList.toggle('hidden');
    }

    function showImportModal() {
        document.getElementById('import-modal').classList.remove('hidden');
    }

    function hideImportModal() {
        document.getElementById('import-modal').classList.add('hidden');
    }

    function showDuplicateModal(id, name) {
        const modal = document.getElementById('duplicate-modal');
        const form = document.getElementById('duplicate-form');
        const newCode = document.getElementById('new_code');
        const newName = document.getElementById('new_name');
        
        form.action = `/admin/vessel-types/${id}/duplicate`;
        newCode.value = '';
        newName.value = `Copia de ${name}`;
        
        modal.classList.remove('hidden');
    }

    function hideDuplicateModal() {
        document.getElementById('duplicate-modal').classList.add('hidden');
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.vessel-type-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        
        updateSelection();
    }

    function updateSelection() {
        const checkboxes = document.querySelectorAll('.vessel-type-checkbox:checked');
        const bulkActions = document.getElementById('bulk-actions');
        const selectedCount = document.getElementById('selected-count');
        
        if (checkboxes.length > 0) {
            bulkActions.classList.remove('hidden');
            selectedCount.textContent = `${checkboxes.length} tipos seleccionados`;
        } else {
            bulkActions.classList.add('hidden');
        }
    }

    function clearSelection() {
        const checkboxes = document.querySelectorAll('.vessel-type-checkbox');
        const selectAll = document.getElementById('select-all');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAll.checked = false;
        
        updateSelection();
    }

    function bulkAction(action) {
        const checkboxes = document.querySelectorAll('.vessel-type-checkbox:checked');
        const ids = Array.from(checkboxes).map(cb => cb.value);
        
        if (ids.length === 0) {
            alert('Selecciona al menos un tipo de embarcación');
            return;
        }
        
        const actions = {
            'activate': 'activar',
            'deactivate': 'desactivar', 
            'mark_common': 'marcar como comunes',
            'mark_specialized': 'marcar como especializados',
            'delete': 'eliminar'
        };
        
        if (confirm(`¿Confirmar ${actions[action]} ${ids.length} tipos de embarcación?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.vessel-types.bulk-action") }}';
            
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
                idInput.name = 'vessel_type_ids[]';
                idInput.value = id;
                form.appendChild(idInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script> 
</x-app-layout>