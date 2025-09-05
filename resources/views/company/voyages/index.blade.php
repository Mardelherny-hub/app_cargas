<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Viajes') }}
            </h2>
            <a href="{{ route('company.voyages.create') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Nuevo Viaje
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- mensajes de eror --}}
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Error:</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class=" font-bold">Éxito:</strong>
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            
            {{-- Estadísticas Rápidas --}}
            @if(isset($stats))
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 font-medium text-sm">{{ $stats['total'] }}</span>
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500">Total</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                <span class="text-yellow-600 font-medium text-sm">{{ $stats['planning'] }}</span>
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500">Planificación</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="text-green-600 font-medium text-sm">{{ $stats['approved'] ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500">Aprobados</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 font-medium text-sm">{{ $stats['this_month'] }}</span>
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-500">Este Mes</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Filtros --}}
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4">
                    <form method="GET" action="{{ route('company.voyages.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <input type="text" name="search" value="{{ request('search') }}" 
                                   placeholder="Buscar por número de viaje..." 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todos los estados</option>
                                <option value="planning" {{ request('status') == 'planning' ? 'selected' : '' }}>Planificación</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Aprobado</option>
                                <option value="in_transit" {{ request('status') == 'in_transit' ? 'selected' : '' }}>En Tránsito</option>
                                <option value="at_destination" {{ request('status') == 'at_destination' ? 'selected' : '' }}>En Destino</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completado</option>
                            </select>
                        </div>
                        
                        <div>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                Filtrar
                            </button>
                            <a href="{{ route('company.voyages.index') }}" 
                               class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                                Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tabla de Viajes Optimizada --}}
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                @if($voyages->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        N° Viaje
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ruta & Fechas
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Embarcación
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cargas
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($voyages as $voyage)
                                    <tr class="hover:bg-gray-50">
                                        {{-- N° Viaje --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <a href="{{ route('company.voyages.show', $voyage) }}" 
                                                   class="text-blue-600 hover:text-blue-500">
                                                    {{ $voyage->voyage_number }}
                                                </a>
                                            </div>
                                            @if($voyage->internal_reference)
                                                <div class="text-xs text-gray-500">
                                                    Ref: {{ $voyage->internal_reference }}
                                                </div>
                                            @endif
                                            <a href="{{ route('company.voyages.detail', $voyage) }}" 
                                               class="text-xs text-red-600 hover:text-red-500">
                                               🎯 Cockpit
                                            </a>
                                        </td>

                                        {{-- Ruta & Fechas (Combinado) --}}
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <div class="font-medium">
                                                    {{ $voyage->originPort->name ?? 'Puerto origen' }} 
                                                    <span class="text-gray-400">→</span> 
                                                    {{ $voyage->destinationPort->name ?? 'Puerto destino' }}
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    {{ $voyage->originPort->country->alpha2_code ?? 'AR' }} → 
                                                    {{ $voyage->destinationPort->country->alpha2_code ?? 'AR' }}
                                                </div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-2">
                                                <div>
                                                    <span class="font-medium">Salida:</span> 
                                                    {{ $voyage->departure_date ? $voyage->departure_date->format('d/m/Y H:i') : 'Por definir' }}
                                                </div>
                                                <div>
                                                    <span class="font-medium">Llegada:</span> 
                                                    {{ $voyage->estimated_arrival_date ? $voyage->estimated_arrival_date->format('d/m/Y H:i') : 'Por definir' }}
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Embarcación --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ $voyage->leadVessel->name ?? 'No especificada' }}
                                            </div>
                                            @if($voyage->leadVessel)
                                                <div class="text-xs text-gray-500">
                                                    {{ $voyage->leadVessel->vesselType->name ?? 'Tipo no definido' }}
                                                </div>
                                            @endif
                                            @if($voyage->captain)
                                                <div class="text-xs text-gray-500 mt-1">
                                                    Cap: {{ $voyage->captain->full_name }}
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Estado (Livewire Component) --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @livewire('voyage-status-badge', ['voyage' => $voyage], key($voyage->id))
                                        </td>

                                        {{-- Cargas (Corregido) --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <span class="font-medium">{{ $voyage->shipments->count() }}</span> cargas
                                            </div>
                                            @if($voyage->shipments->count() > 0)
                                                @php
                                                    $totalBills = $voyage->shipments->sum(function($shipment) {
                                                        return $shipment->billsOfLading->count();
                                                    });
                                                @endphp
                                                <div class="text-xs text-gray-500">
                                                    {{ $totalBills }} conocimientos
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Acciones --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ route('company.voyages.show', $voyage) }}" 
                                                   class="text-blue-600 hover:text-blue-900" title="Ver detalle">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </a>
                                                
                                                @if($voyage->status === 'planning')
                                                    <a href="{{ route('company.voyages.edit', $voyage) }}" 
                                                       class="text-yellow-600 hover:text-yellow-900" title="Editar">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </a>
                                                @endif

                                                <a href="{{ route('company.shipments.index', ['voyage_id' => $voyage->id]) }}" 
                                                   class="text-green-600 hover:text-green-900" title="Ver cargas">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginación --}}
                    @if(method_exists($voyages, 'links'))
                        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                            {{ $voyages->appends(request()->query())->links() }}
                        </div>
                    @endif

                @else
                    {{-- Estado vacío --}}
                    <div class="text-center py-12">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900">No hay viajes registrados</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            @if(request()->hasAny(['search', 'status', 'date_from']))
                                No se encontraron viajes con los filtros aplicados.
                            @else
                                Comienza creando tu primer viaje para empezar a planificar las rutas de tu empresa.
                            @endif
                        </p>
                        
                        @if(!request()->hasAny(['search', 'status', 'date_from']))
                            <div class="mt-6">
                                <a href="{{ route('company.voyages.create') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Crear primer viaje
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Livewire Scripts --}}
    @livewireScripts
    
    {{-- Listener para eventos del StatusBadge --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Livewire.on('voyage-status-changed', (voyageId, newStatus) => {
                // Mostrar notificación de éxito
                console.log(`Viaje ${voyageId} cambió a estado: ${newStatus}`);
                
                // Opcional: Refrescar la página o actualizar otras partes de la UI
                // window.location.reload();
            });
        });
    </script>
</x-app-layout>