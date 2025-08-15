<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.voyages.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Viaje {{ $voyage->voyage_number ?? 'Sin número' }}
                </h2>
            </div>

            <div class="flex items-center space-x-2">
                @if($voyage->status === 'planning')
                    <a href="{{ route('company.voyages.edit', $voyage) }}" 
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar
                    </a>
                @endif

                @if(in_array($voyage->status, ['planning', 'approved']))
                    <a href="{{ route('company.voyages.pdf', $voyage) }}" 
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        PDF
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Información General -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Información General
                        </h3>
                        @livewire('status-changer', ['model' => $voyage])
                    </div>

                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Número de Viaje</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $voyage->voyage_number ?? 'Sin número' }}</dd>
                        </div>

                        @if($voyage->internal_reference)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Referencia Interna</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->internal_reference }}</dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tipo de Viaje</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->voyage_type ?? 'No especificado' }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tipo de Carga</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->cargo_type ?? 'No especificado' }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Fecha de Salida</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->departure_date ? $voyage->departure_date->format('d/m/Y H:i') : 'No definida' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Llegada Estimada</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->estimated_arrival_date ? $voyage->estimated_arrival_date->format('d/m/Y H:i') : 'No definida' }}
                            </dd>
                        </div>

                        @if($voyage->actual_arrival_date)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Llegada Real</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->actual_arrival_date->format('d/m/Y H:i') }}</dd>
                        </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-gray-500">¿Es Convoy?</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->is_convoy ? 'Sí' : 'No' }}</dd>
                        </div>

                        @if($voyage->vessel_count > 1)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Número de Embarcaciones</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $voyage->vessel_count }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Embarcación y Capitán -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Embarcación y Capitán
                    </h3>

                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Embarcación Líder</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($voyage->leadVessel)
                                    <div class="font-semibold">{{ $voyage->leadVessel->name }}</div>
                                    @if($voyage->leadVessel->vesselOwner)
                                        <div class="text-gray-600 text-xs">{{ $voyage->leadVessel->vesselOwner->legal_name }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">No especificada</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Capitán</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($voyage->captain)
                                    <div class="font-semibold">{{ $voyage->captain->full_name }}</div>
                                    @if($voyage->captain->license_number)
                                        <div class="text-gray-600 text-xs">Licencia: {{ $voyage->captain->license_number }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">No asignado</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Información de Ruta -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Información de Ruta
                    </h3>

                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Puerto de Origen</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($voyage->originPort)
                                    <div class="font-semibold">{{ $voyage->originPort->name }}</div>
                                    @if($voyage->originCountry)
                                        <div class="text-gray-600 text-xs">{{ $voyage->originCountry->name }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">No especificado</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Puerto de Destino</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($voyage->destinationPort)
                                    <div class="font-semibold">{{ $voyage->destinationPort->name }}</div>
                                    @if($voyage->destinationCountry)
                                        <div class="text-gray-600 text-xs">{{ $voyage->destinationCountry->name }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">No especificado</span>
                                @endif
                            </dd>
                        </div>

                        @if($voyage->transshipmentPort)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Puerto de Transbordo</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <div class="font-semibold">{{ $voyage->transshipmentPort->name }}</div>
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Cargas/Envíos Asociados -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Cargas Asociadas ({{ $voyage->shipments->count() }})
                        </h3>
                        @if($voyage->status === 'planning')
                            <div class="flex items-center space-x-3">
                                <!-- Botón Gestionar Shipments -->
                                <a href="{{ route('company.shipments.create', ['voyage_id' => $voyage->id]) }}"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Nueva Carga
                                </a>
                                
                                <!-- Botón Agregar Contenedores 
                                <a href="{{ route('company.voyages.containers', $voyage) }}" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    Gestionar Contenedores
                                </a>-->
                            </div>
                        @endif
                    </div>
                </div>
                <!-- Lista de Shipments existentes -->
                @if($voyage->shipments->count() > 0)
                    <div class="mt-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Shipment
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Embarcación
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($voyage->shipments as $shipment)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $shipment->shipment_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $shipment->vessel->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ ucfirst($shipment->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium relative z-10">
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ route('company.shipments.show', $shipment) }}"
                                                class="text-indigo-600 hover:text-indigo-900">Ver</a>
                                                @livewire('status-changer', [
                                                    'model' => $shipment,
                                                    'modelType' => 'shipment'
                                                ], key($shipment->id))
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="mt-4 text-center py-8">
                        <p class="text-gray-500 text-sm">No hay shipments creados para este viaje.</p>
                        <p class="text-gray-400 text-xs mt-1">Usa el botón "Gestionar Shipments" para crear el primero.</p>
                    </div>
                @endif
            </div>

            <!-- Información Adicional -->
            @if($voyage->special_instructions || $voyage->operational_notes)
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Información Adicional
                    </h3>

                    @if($voyage->special_instructions)
                    <div class="mb-4">
                        <dt class="text-sm font-medium text-gray-500 mb-1">Instrucciones Especiales</dt>
                        <dd class="text-sm text-gray-900 bg-yellow-50 p-3 rounded-md">{{ $voyage->special_instructions }}</dd>
                    </div>
                    @endif

                    @if($voyage->operational_notes)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 mb-1">Notas Operacionales</dt>
                        <dd class="text-sm text-gray-900 bg-gray-50 p-3 rounded-md">{{ $voyage->operational_notes }}</dd>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Información de Auditoría -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Información de Auditoría
                    </h3>

                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Creado por</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->createdByUser->name ?? 'Usuario no disponible' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Fecha de Creación</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->created_date ? $voyage->created_date->format('d/m/Y H:i') : $voyage->created_at->format('d/m/Y H:i') }}
                            </dd>
                        </div>

                        @if($voyage->last_updated_date && $voyage->last_updated_date != $voyage->created_date)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Última Actualización</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $voyage->last_updated_date->format('d/m/Y H:i') }}
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>