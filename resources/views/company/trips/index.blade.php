<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Gestión de Viajes') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('company.trips.create') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Nuevo Viaje
                </a>
                <a href="{{-- route('company.reports.trips') --}}#"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Reportes
                </a>
                <a href="{{-- route('company.trips.calendar') --}}#"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Calendario
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Estadísticas rápidas -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-2">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-500">Total</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-2">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-500">Planificados</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $stats['planned'] ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-500 rounded-md p-2">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-500">En Curso</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $stats['in_progress'] ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-2">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-500">Completados</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $stats['completed'] ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-orange-500 rounded-md p-2">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-500">Cargas</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $stats['total_shipments'] ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros y Búsqueda -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('company.trips.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Búsqueda general -->
                            <div class="md:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                                <div class="mt-1 relative">
                                    <input type="text"
                                           name="search"
                                           id="search"
                                           value="{{ request('search') }}"
                                           placeholder="Número de viaje, ruta, embarcación..."
                                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Estado -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select name="status" id="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Todos los estados</option>
                                    <option value="planned" {{ request('status') === 'planned' ? 'selected' : '' }}>Planificado</option>
                                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>En Curso</option>
                                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completado</option>
                                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                                    <option value="delayed" {{ request('status') === 'delayed' ? 'selected' : '' }}>Retrasado</option>
                                </select>
                            </div>

                            <!-- Operador -->
                            <div>
                                <label for="operator" class="block text-sm font-medium text-gray-700">Operador</label>
                                <select name="operator" id="operator" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Todos los operadores</option>
                                    @foreach($operators ?? [] as $operator)
                                        <option value="{{ $operator->id }}" {{ request('operator') == $operator->id ? 'selected' : '' }}>
                                            {{ $operator->first_name }} {{ $operator->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Fecha desde -->
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">Fecha desde</label>
                                <input type="date"
                                       name="date_from"
                                       id="date_from"
                                       value="{{ request('date_from') }}"
                                       class="mt-1 shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            </div>

                            <!-- Fecha hasta -->
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">Fecha hasta</label>
                                <input type="date"
                                       name="date_to"
                                       id="date_to"
                                       value="{{ request('date_to') }}"
                                       class="mt-1 shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            </div>

                            <!-- Ruta -->
                            <div>
                                <label for="route" class="block text-sm font-medium text-gray-700">Ruta</label>
                                <select name="route" id="route" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Todas las rutas</option>
                                    <option value="AR-PY" {{ request('route') === 'AR-PY' ? 'selected' : '' }}>Argentina → Paraguay</option>
                                    <option value="PY-AR" {{ request('route') === 'PY-AR' ? 'selected' : '' }}>Paraguay → Argentina</option>
                                    <option value="AR-AR" {{ request('route') === 'AR-AR' ? 'selected' : '' }}>Cabotaje Argentina</option>
                                    <option value="PY-PY" {{ request('route') === 'PY-PY' ? 'selected' : '' }}>Cabotaje Paraguay</option>
                                </select>
                            </div>

                            <!-- Botones -->
                            <div class="flex items-end space-x-2">
                                <button type="submit"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Filtrar
                                </button>
                                <a href="{{ route('company.trips.index') }}"
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                    Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Viajes -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    @if(isset($trips) && $trips->count() > 0)
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'number', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}" class="group inline-flex">
                                            N° Viaje
                                            <span class="ml-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                                </svg>
                                            </span>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Embarcación/Ruta
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Origen → Destino
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fechas
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cargas
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Operador
                                    </th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($trips as $trip)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <a href="{{ route('company.trips.show', $trip) }}" class="text-blue-600 hover:text-blue-500">
                                                #{{ $trip->number ?? 'N/A' }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ $trip->vessel_name ?? 'Embarcación no especificada' }}</div>
                                            <div class="text-sm text-gray-500">{{ $trip->route_name ?? 'Ruta no definida' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ $trip->origin_port ?? 'Puerto origen' }} → {{ $trip->destination_port ?? 'Puerto destino' }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $trip->origin_country ?? 'AR' }} → {{ $trip->destination_country ?? 'AR' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <span class="font-medium">Salida:</span> {{ $trip->departure_date ? $trip->departure_date->format('d/m/Y H:i') : 'Por definir' }}
                                            </div>
                                            @if($trip->arrival_date)
                                                <div class="text-sm text-gray-500">
                                                    <span class="font-medium">Llegada:</span> {{ $trip->arrival_date->format('d/m/Y H:i') }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $statusColors = [
                                                    'planned' => 'bg-gray-100 text-gray-800',
                                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                                    'completed' => 'bg-green-100 text-green-800',
                                                    'cancelled' => 'bg-red-100 text-red-800',
                                                    'delayed' => 'bg-yellow-100 text-yellow-800',
                                                ];
                                                $statusLabels = [
                                                    'planned' => 'Planificado',
                                                    'in_progress' => 'En Curso',
                                                    'completed' => 'Completado',
                                                    'cancelled' => 'Cancelado',
                                                    'delayed' => 'Retrasado',
                                                ];
                                                $status = $trip->status ?? 'planned';
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ $statusLabels[$status] ?? ucfirst($status) }}
                                            </span>

                                            @if($trip->status === 'in_progress' && $trip->estimated_arrival)
                                                <div class="text-xs text-gray-500 mt-1">
                                                    ETA: {{ $trip->estimated_arrival->format('d/m H:i') }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <span class="font-medium text-gray-900">{{ $trip->shipments_count ?? 0 }}</span>
                                                @if(($trip->shipments_count ?? 0) > 0)
                                                    <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                                        {{ number_format($trip->total_weight ?? 0, 0) }} kg
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $trip->operator ? $trip->operator->first_name . ' ' . $trip->operator->last_name : 'Sin asignar' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ route('company.trips.show', $trip) }}"
                                                   class="text-blue-600 hover:text-blue-500"
                                                   title="Ver detalles">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </a>

                                                @if(in_array($trip->status ?? 'planned', ['planned', 'delayed']))
                                                    <a href="{{ route('company.trips.edit', $trip) }}"
                                                       class="text-yellow-600 hover:text-yellow-500"
                                                       title="Editar">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </a>
                                                @endif

                                                <a href="{{ route('company.trips.tracking', $trip) }}"
                                                   class="text-green-600 hover:text-green-500"
                                                   title="Seguimiento">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                </a>

                                                @if($trip->status === 'planned')
                                                    <button onclick="startTrip({{ $trip->id }})"
                                                            class="text-purple-600 hover:text-purple-500"
                                                            title="Iniciar viaje">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                    </button>
                                                @elseif($trip->status === 'in_progress')
                                                    <button onclick="completeTrip({{ $trip->id }})"
                                                            class="text-green-600 hover:text-green-500"
                                                            title="Completar viaje">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                    </button>
                                                @endif

                                                <a href="{{ route('company.trips.manifest', $trip) }}"
                                                   class="text-indigo-600 hover:text-indigo-500"
                                                   title="Manifiesto">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Paginación -->
                        @if(method_exists($trips, 'links'))
                            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                                {{ $trips->appends(request()->query())->links() }}
                            </div>
                        @endif

                    @else
                        <!-- Estado vacío -->
                        <div class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">No hay viajes registrados</h3>
                            <p class="mt-2 text-sm text-gray-500">
                                @if(request()->hasAny(['search', 'status', 'operator', 'date_from', 'date_to', 'route']))
                                    No se encontraron viajes con los filtros aplicados.
                                @else
                                    Comienza creando tu primer viaje para empezar a planificar las rutas de tu empresa.
                                @endif
                            </p>
                            <div class="mt-6">
                                @if(request()->hasAny(['search', 'status', 'operator', 'date_from', 'date_to', 'route']))
                                    <a href="{{ route('company.trips.index') }}"
                                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Limpiar filtros
                                    </a>
                                @else
                                    <a href="{{ route('company.trips.create') }}"
                                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Crear primer viaje
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function startTrip(tripId) {
            if (confirm('¿Estás seguro de que quieres iniciar este viaje? Una vez iniciado, se registrará la hora de salida.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/company/trips/${tripId}/start`;

                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = '{{ csrf_token() }}';
                form.appendChild(token);

                const method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                method.value = 'PATCH';
                form.appendChild(method);

                document.body.appendChild(form);
                form.submit();
            }
        }

        function completeTrip(tripId) {
            if (confirm('¿Estás seguro de que quieres completar este viaje? Se registrará la hora de llegada y se cerrarán todas las cargas asociadas.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/company/trips/${tripId}/complete`;

                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = '{{ csrf_token() }}';
                form.appendChild(token);

                const method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                method.value = 'PATCH';
                form.appendChild(method);

                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-submit form en cambio de filtros principales
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('operator').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('route').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
    @endpush
</x-app-layout>
