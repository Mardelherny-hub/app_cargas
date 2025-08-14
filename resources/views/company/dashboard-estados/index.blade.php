<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📊 Dashboard de Estados
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Vista consolidada de estados en todo el sistema
                </p>
            </div>
            <div>
                <a href="{{ route('company.dashboard-estados.export') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Exportar Reporte
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Filtros -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Filtros</h3>
                    <form method="GET" action="{{ route('company.dashboard-estados.index') }}">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label for="entity_type" class="block text-sm font-medium text-gray-700">Tipo de Entidad</label>
                                <select id="entity_type" name="entity_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="all" {{ $filters['entity_type'] == 'all' ? 'selected' : '' }}>Todos</option>
                                    <option value="voyages" {{ $filters['entity_type'] == 'voyages' ? 'selected' : '' }}>Viajes</option>
                                    <option value="shipments" {{ $filters['entity_type'] == 'shipments' ? 'selected' : '' }}>Cargas</option>
                                    <option value="bills_of_lading" {{ $filters['entity_type'] == 'bills_of_lading' ? 'selected' : '' }}>Bills of Lading</option>
                                    <option value="shipment_items" {{ $filters['entity_type'] == 'shipment_items' ? 'selected' : '' }}>Items</option>
                                </select>
                            </div>

                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                                <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="all" {{ $filters['status'] == 'all' ? 'selected' : '' }}>Todos</option>
                                    <option value="draft">Borrador</option>
                                    <option value="planning">Planificación</option>
                                    <option value="confirmed">Confirmado</option>
                                    <option value="verified">Verificado</option>
                                    <option value="in_transit">En Tránsito</option>
                                    <option value="completed">Completado</option>
                                </select>
                            </div>

                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">Desde</label>
                                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">Hasta</label>
                                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" 
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                </svg>
                                Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Métricas principales por entidad -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Viajes -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Viajes</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $metrics['voyages']['total'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm space-y-1">
                            @foreach($metrics['voyages']['status_counts'] as $status => $count)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ $metrics['voyages']['status_labels'][$status] ?? $status }}:</span>
                                    <span class="font-medium">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Cargas -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Cargas</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $metrics['shipments']['total'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm space-y-1">
                            @foreach($metrics['shipments']['status_counts'] as $status => $count)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ $metrics['shipments']['status_labels'][$status] ?? $status }}:</span>
                                    <span class="font-medium">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Bills of Lading -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Bills of Lading</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $metrics['bills_of_lading']['total'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm space-y-1">
                            @foreach($metrics['bills_of_lading']['status_counts'] as $status => $count)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ $metrics['bills_of_lading']['status_labels'][$status] ?? $status }}:</span>
                                    <span class="font-medium">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Items</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $metrics['shipment_items']['total'] }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm space-y-1">
                            @foreach($metrics['shipment_items']['status_counts'] as $status => $count)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ $metrics['shipment_items']['status_labels'][$status] ?? $status }}:</span>
                                    <span class="font-medium">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos de distribución -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Distribución por entidad -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">📈 Distribución por Entidad</h3>
                        <div class="space-y-4">
                            @foreach($statusDistribution as $entity => $statuses)
                                <div>
                                    <div class="flex justify-between text-sm font-medium text-gray-700 mb-2">
                                        <span>{{ ucwords(str_replace('_', ' ', $entity)) }}</span>
                                        <span>{{ array_sum($statuses) }} total</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        @php
                                            $total = array_sum($statuses);
                                            $colors = ['bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-red-500', 'bg-purple-500', 'bg-indigo-500', 'bg-pink-500', 'bg-gray-500'];
                                            $colorIndex = 0;
                                        @endphp
                                        @if($total > 0)
                                            @foreach($statuses as $status => $count)
                                                @php
                                                    $percentage = ($count / $total) * 100;
                                                    $color = $colors[$colorIndex % count($colors)];
                                                    $colorIndex++;
                                                @endphp
                                                <div class="{{ $color }} h-2 rounded-full inline-block" 
                                                     style="width: {{ $percentage }}%"
                                                     title="{{ $status }}: {{ $count }}"></div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Cambios recientes -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">🕒 Cambios Recientes</h3>
                        <div class="space-y-3">
                            @forelse($recentChanges as $change)
                                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-shrink-0">
                                        @switch($change['type'])
                                            @case('voyage')
                                                <div class="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                                    </svg>
                                                </div>
                                                @break
                                            @case('shipment')
                                                <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                                    </svg>
                                                </div>
                                                @break
                                            @default
                                                <div class="w-6 h-6 bg-gray-500 rounded-full"></div>
                                        @endswitch
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ ucwords(str_replace('_', ' ', $change['type'])) }}
                                            @if(isset($change['entity']->voyage_number))
                                                {{ $change['entity']->voyage_number }}
                                            @elseif(isset($change['entity']->shipment_number))
                                                {{ $change['entity']->shipment_number }}
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            Estado: <span class="font-medium">{{ ucwords(str_replace('_', ' ', $change['status'])) }}</span>
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 text-xs text-gray-400">
                                        {{ $change['updated_at']->diffForHumans() }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4">
                                    <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">No hay cambios recientes</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Componente Livewire para gestión interactiva -->
            @livewire('dashboard-estados-manager', [
                'filters' => $filters
            ])
        </div>

    </div>
</x-app-layout>