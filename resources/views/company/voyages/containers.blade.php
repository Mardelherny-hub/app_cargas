<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.voyages.show', $voyage) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Gestión de Contenedores
                    </h2>
                    <p class="text-sm text-gray-600">{{ $voyage->voyage_number }}</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Información del Viaje -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Información del Viaje
                    </h3>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Embarcación Líder</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->leadVessel->name ?? 'No especificada' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Ruta</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->originPort->code ?? 'N/A' }} → {{ $voyage->destinationPort->code ?? 'N/A' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Estado</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($voyage->status === 'planning') bg-yellow-100 text-yellow-800
                                    @elseif($voyage->status === 'loading') bg-blue-100 text-blue-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ ucfirst($voyage->status) }}
                                </span>
                            </dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Capacidad por Shipment -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Capacidad por Embarcación
                    </h3>
                    @if($shipmentStats->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Embarcación
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Shipment
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Capacidad
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cargados
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Disponible
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Utilización
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($shipmentStats as $stat)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $stat['vessel_name'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $stat['shipment_number'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $stat['container_capacity'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $stat['containers_loaded'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $stat['available_capacity'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @php
                                                    $percentage = $stat['container_capacity'] > 0 
                                                        ? round(($stat['containers_loaded'] / $stat['container_capacity']) * 100) 
                                                        : 0;
                                                @endphp
                                                <div class="flex items-center">
                                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                        <div class="h-2 rounded-full 
                                                            @if($percentage >= 90) bg-red-600
                                                            @elseif($percentage >= 70) bg-yellow-500
                                                            @else bg-green-600 @endif" 
                                                            style="width: {{ $percentage }}%"></div>
                                                    </div>
                                                    <span class="text-sm text-gray-600">{{ $percentage }}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <!-- Mensaje cuando no hay shipments -->
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay shipments configurados</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Para agregar contenedores, primero debe crear shipments (cargas) para este viaje.
                            </p>
                            <div class="mt-6">
                                <a href="{{ route('company.shipments.create', ['voyage_id' => $voyage->id]) }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Crear Primer Shipment
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contenedores Existentes - Solo mostrar si hay shipments -->
            @if($shipmentStats->count() > 0)
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Contenedores del Viaje ({{ $existingContainers->count() }})
                        </h3>
                        @if($shipmentStats->sum('available_capacity') > 0)
                            <button type="button" 
                                    onclick="document.getElementById('add-container-form').style.display='block'"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Agregar Contenedor
                            </button>
                        @endif
                    </div>

                    @if($existingContainers->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Número
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Condición
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Peso Bruto
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Puerto Actual
                                        </th>
                                        <th class="relative px-6 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($existingContainers as $container)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $container->container_number }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $container->containerType->name ?? 'No especificado' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @if($container->condition === 'L') bg-green-100 text-green-800
                                                    @elseif($container->condition === 'V') bg-gray-100 text-gray-800
                                                    @elseif($container->condition === 'D') bg-red-100 text-red-800
                                                    @else bg-yellow-100 text-yellow-800 @endif">
                                                    {{ $container->condition }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $container->current_gross_weight_kg ? number_format($container->current_gross_weight_kg, 0) . ' kg' : 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $container->currentPort->name ?? 'No especificado' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <form method="POST" action="{{ route('company.voyages.remove-container', [$voyage, $container]) }}" 
                                                      onsubmit="return confirm('¿Está seguro de quitar este contenedor del viaje?')"
                                                      class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        Quitar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay contenedores</h3>
                            <p class="mt-1 text-sm text-gray-500">Comience agregando contenedores a este viaje.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Formulario para Agregar Contenedor - Solo mostrar si hay shipments -->
            <div id="add-container-form" style="display: none;" class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Agregar Contenedor
                        </h3>
                        <button type="button" 
                                onclick="document.getElementById('add-container-form').style.display='none'"
                                class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('company.voyages.add-container', $voyage) }}">
                        @csrf
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Número de Contenedor -->
                            <div>
                                <label for="container_number" class="block text-sm font-medium text-gray-700">
                                    Número de Contenedor <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="container_number" 
                                       id="container_number" 
                                       required 
                                       maxlength="15"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                       placeholder="Ej: TCLU1234567">
                            </div>

                            <!-- Shipment -->
                            <div>
                                <label for="shipment_id" class="block text-sm font-medium text-gray-700">
                                    Asignar a Shipment <span class="text-red-500">*</span>
                                </label>
                                <select name="shipment_id" 
                                        id="shipment_id" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Seleccione un shipment</option>
                                    @foreach($shipmentStats as $stat)
                                        @if($stat['available_capacity'] > 0)
                                            <option value="{{ $stat['id'] }}">
                                                {{ $stat['vessel_name'] }} - {{ $stat['shipment_number'] }} 
                                                ({{ $stat['available_capacity'] }} disponibles)
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end space-x-4">
                            <button type="button" 
                                    onclick="document.getElementById('add-container-form').style.display='none'"
                                    class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                Agregar Contenedor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>