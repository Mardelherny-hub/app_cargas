{{-- resources/views/company/shipments/show.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Carga #{{ $shipment->shipment_number }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Viaje: {{ $shipment->voyage->voyage_number }} | 
                    Embarcación: {{ $shipment->vessel->name }} |
                    Secuencia: {{ $shipment->sequence_in_voyage }}
                </p>
            </div>
            <div class="flex space-x-3">
                @if($userPermissions['can_edit'])
                    <a href="{{ route('company.shipments.edit', $shipment) }}" 
                       class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                        Editar
                    </a>
                @endif
                <a href="{{ route('company.shipments.index') }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Volver a Lista
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Estado y Acciones Rápidas --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full 
                                @switch($shipment->status)
                                    @case('planning')
                                        bg-gray-100 text-gray-800
                                        @break
                                    @case('loading')
                                        bg-yellow-100 text-yellow-800
                                        @break
                                    @case('loaded')
                                        bg-blue-100 text-blue-800
                                        @break
                                    @case('in_transit')
                                        bg-indigo-100 text-indigo-800
                                        @break
                                    @case('arrived')
                                        bg-green-100 text-green-800
                                        @break
                                    @case('discharging')
                                        bg-orange-100 text-orange-800
                                        @break
                                    @case('completed')
                                        bg-green-100 text-green-800
                                        @break
                                    @case('delayed')
                                        bg-red-100 text-red-800
                                        @break
                                @endswitch
                            ">
                                @switch($shipment->status)
                                    @case('planning') Planificación @break
                                    @case('loading') Cargando @break
                                    @case('loaded') Cargado @break
                                    @case('in_transit') En Tránsito @break
                                    @case('arrived') Arribado @break
                                    @case('discharging') Descargando @break
                                    @case('completed') Completado @break
                                    @case('delayed') Demorado @break
                                @endswitch
                            </span>

                            {{-- Alertas --}}
                            @if($shipment->requires_attention)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Requiere Atención
                                </span>
                            @endif
                            
                            @if($shipment->has_delays)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                    </svg>
                                    Con Demoras
                                </span>
                            @endif
                        </div>

                        {{-- Cambio de Estado --}}
                        @if($userPermissions['can_change_status'] && !empty($statusTransitions))
                            <div class="flex space-x-2">
                                @foreach($statusTransitions as $newStatus => $label)
                                    <form method="POST" action="{{ route('company.shipments.update-status', $shipment) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $newStatus }}">
                                        <button type="submit" 
                                                class="bg-indigo-500 hover:bg-indigo-700 text-white text-sm font-bold py-1 px-3 rounded"
                                                onclick="return confirm('¿Confirma cambiar el estado a {{ $label }}?')">
                                            {{ $label }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Estadísticas Principales --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Items</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $stats['total_items'] }}</dd>
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
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Bultos</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['total_packages']) }}</dd>
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
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-1m-3 1l-3-1"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Peso Bruto</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['total_gross_weight_kg'] / 1000, 1) }}t</dd>
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
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2 2z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Utilización</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['cargo_utilization_percentage'], 1) }}%</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Información Principal --}}
                <div class="lg:col-span-2 space-y-6">
                    
                    {{-- Información del Viaje y Embarcación --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Información de Viaje y Embarcación</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {{-- Información del Viaje --}}
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">Viaje</h4>
                                    <dl class="space-y-2">
                                        <div>
                                            <dt class="text-xs text-gray-500">Número de Viaje</dt>
                                            <dd class="text-sm text-gray-900">
                                                <a href="{{ route('company.voyages.show', $shipment->voyage) }}" 
                                                   class="text-blue-600 hover:text-blue-500">
                                                    {{ $shipment->voyage->voyage_number }}
                                                </a>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Ruta</dt>
                                            <dd class="text-sm text-gray-900">
                                                {{ $shipment->voyage->originPort->name }} → {{ $shipment->voyage->destinationPort->name }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Países</dt>
                                            <dd class="text-sm text-gray-900">
                                                {{ $shipment->voyage->originCountry->name }} → {{ $shipment->voyage->destinationCountry->name }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Fecha de Salida</dt>
                                            <dd class="text-sm text-gray-900">{{ $shipment->voyage->departure_date->format('d/m/Y H:i') }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                {{-- Información de la Embarcación --}}
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">Embarcación</h4>
                                    <dl class="space-y-2">
                                        <div>
                                            <dt class="text-xs text-gray-500">Nombre</dt>
                                            <dd class="text-sm text-gray-900">{{ $shipment->vessel->name }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Tipo</dt>
                                            <dd class="text-sm text-gray-900">{{ $shipment->vessel->vesselType->name ?? 'N/A' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Propietario</dt>
                                            <dd class="text-sm text-gray-900">{{ $shipment->vessel->owner->legal_name ?? 'N/A' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Rol en Convoy</dt>
                                            <dd class="text-sm text-gray-900">
                                                @switch($shipment->vessel_role)
                                                    @case('single') Embarcación Única @break
                                                    @case('lead') Líder @break
                                                    @case('towed') Remolcada @break
                                                    @case('pushed') Empujada @break
                                                    @case('escort') Escolta @break
                                                    @default {{ ucfirst($shipment->vessel_role) }}
                                                @endswitch
                                                @if($shipment->convoy_position)
                                                    (Posición: {{ $shipment->convoy_position }})
                                                @endif
                                                @if($shipment->is_lead_vessel)
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        Líder
                                                    </span>
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            {{-- Información del Capitán --}}
                            @if($shipment->captain)
                                <div class="mt-6 pt-6 border-t border-gray-200">
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">Capitán</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <dt class="text-xs text-gray-500">Nombre Completo</dt>
                                            <dd class="text-sm text-gray-900">{{ $shipment->captain->full_name }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Licencia</dt>
                                            <dd class="text-sm text-gray-900">
                                                {{ $shipment->captain->license_number }}
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    @if($shipment->captain->license_status === 'valid') bg-green-100 text-green-800 @else bg-red-100 text-red-800 @endif">
                                                    {{ ucfirst($shipment->captain->license_status) }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Experiencia</dt>
                                            <dd class="text-sm text-gray-900">{{ $shipment->captain->years_of_experience }} años</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Contacto</dt>
                                            <dd class="text-sm text-gray-900">
                                                @if($shipment->captain->phone)
                                                    {{ $shipment->captain->phone }}
                                                @endif
                                                @if($shipment->captain->email)
                                                    <br>{{ $shipment->captain->email }}
                                                @endif
                                            </dd>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Capacidades y Utilización --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Capacidades y Utilización</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {{-- Capacidad de Carga --}}
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">Capacidad de Carga</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="flex justify-between text-sm">
                                                <span>Cargado / Capacidad</span>
                                                <span>{{ number_format($shipment->cargo_weight_loaded, 1) }}t / {{ number_format($shipment->cargo_capacity_tons, 1) }}t</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(100, $stats['cargo_utilization_percentage']) }}%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">{{ number_format($stats['cargo_utilization_percentage'], 1) }}% utilizado</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Capacidad de Contenedores --}}
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700 mb-3">Capacidad de Contenedores</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="flex justify-between text-sm">
                                                <span>Cargados / Capacidad</span>
                                                <span>{{ $shipment->containers_loaded }} / {{ $shipment->container_capacity }}</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                                <div class="bg-green-600 h-2 rounded-full" style="width: {{ min(100, $stats['container_utilization_percentage']) }}%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">{{ number_format($stats['container_utilization_percentage'], 1) }}% utilizado</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Items de Mercadería --}}
                    @if($stats['total_items'] > 0)
                        <div class="bg-white shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Items de Mercadería</h3>
                                    @if($userPermissions['can_manage_items'])
                                        <a href="{{ route('company.shipment-items.create', ['shipment' => $shipment->id]) }}" 
                                           class="bg-blue-500 hover:bg-blue-700 text-white text-sm font-bold py-1 px-3 rounded">
                                            Agregar Item
                                        </a>
                                    @endif
                                </div>
                                
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Línea</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de Carga</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peso (kg)</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($shipment->shipmentItems as $item)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {{ $item->line_number }}
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-gray-900">
                                                        <div>{{ $item->item_description }}</div>
                                                        @if($item->commodity_description)
                                                            <div class="text-xs text-gray-500">{{ $item->commodity_description }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $item->cargoType->name ?? 'N/A' }}
                                                        @if($item->is_dangerous_goods)
                                                            <span class="ml-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                Peligroso
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ number_format($item->package_quantity) }}
                                                        @if($item->packagingType)
                                                            <div class="text-xs text-gray-500">{{ $item->packagingType->name }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ number_format($item->gross_weight_kg, 1) }}
                                                        @if($item->net_weight_kg)
                                                            <div class="text-xs text-gray-500">Neto: {{ number_format($item->net_weight_kg, 1) }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                            @if($item->has_discrepancies) bg-red-100 text-red-800 
                                                            @elseif($item->requires_review) bg-yellow-100 text-yellow-800 
                                                            @else bg-green-100 text-green-800 @endif">
                                                            @if($item->has_discrepancies) Con Discrepancias
                                                            @elseif($item->requires_review) Requiere Revisión
                                                            @else OK @endif
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Panel Lateral --}}
                <div class="space-y-6">
                    
                    {{-- Navegación de Shipments --}}
                    @if($voyageShipments->count() > 0)
                        <div class="bg-white shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Otros Shipments del Viaje</h3>
                                <div class="space-y-3">
                                    @foreach($voyageShipments as $otherShipment)
                                        <div class="flex items-center justify-between p-3 border rounded-lg">
                                            <div>
                                                <div class="text-sm font-medium">{{ $otherShipment->shipment_number }}</div>
                                                <div class="text-xs text-gray-500">{{ $otherShipment->vessel->name }}</div>
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                    @switch($otherShipment->status)
                                                        @case('planning') bg-gray-100 text-gray-800 @break
                                                        @case('loading') bg-yellow-100 text-yellow-800 @break
                                                        @case('in_transit') bg-indigo-100 text-indigo-800 @break
                                                        @default bg-green-100 text-green-800
                                                    @endswitch">
                                                    {{ ucfirst($otherShipment->status) }}
                                                </span>
                                                <div class="mt-1">
                                                    <a href="{{ route('company.shipments.show', $otherShipment) }}" 
                                                       class="text-blue-600 hover:text-blue-500 text-xs">Ver</a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Estadísticas Adicionales --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Estadísticas Adicionales</h3>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Mercancías Peligrosas</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['dangerous_goods_count'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Mercancías Perecederas</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['perishable_goods_count'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Requieren Refrigeración</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['refrigerated_goods_count'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Conocimientos de Embarque</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['total_bills_of_lading'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Items con Discrepancias</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['items_with_discrepancies'] }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- Estado de Aprobaciones --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Estado de Aprobaciones</h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Seguridad Aprobada</span>
                                    @if($stats['safety_approved'])
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Aduana Liberada</span>
                                    @if($stats['customs_cleared'])
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Documentación Completa</span>
                                    @if($stats['documentation_complete'])
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Carga Inspeccionada</span>
                                    @if($stats['cargo_inspected'])
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Acciones Adicionales --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Acciones</h3>
                            <div class="space-y-2">
                                @if($userPermissions['can_view_attachments'])
                                    <a href="{{ route('company.shipments.attachments', $shipment) }}" 
                                       class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded text-center block">
                                        Ver Adjuntos
                                        @if($hasAttachments)
                                            <span class="ml-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Disponibles
                                            </span>
                                        @endif
                                    </a>
                                @endif
                                
                                <a href="{{ route('company.shipments.pdf', $shipment) }}" 
                                   class="w-full bg-red-100 hover:bg-red-200 text-red-800 font-medium py-2 px-4 rounded text-center block">
                                    Descargar PDF
                                </a>

                                @if($userPermissions['can_delete'])
                                    <form method="POST" action="{{ route('company.shipments.destroy', $shipment) }}" 
                                          class="inline w-full" onsubmit="return confirm('¿Está seguro de eliminar esta carga? Esta acción no se puede deshacer.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="w-full bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                            Eliminar Carga
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Instrucciones y Notas --}}
            @if($shipment->special_instructions || $shipment->handling_notes)
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Instrucciones y Notas</h3>
                        
                        @if($shipment->special_instructions)
                            <div class="mb-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Instrucciones Especiales</h4>
                                <p class="text-sm text-gray-900 bg-blue-50 p-3 rounded">{{ $shipment->special_instructions }}</p>
                            </div>
                        @endif
                        
                        @if($shipment->handling_notes)
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Notas de Manejo</h4>
                                <p class="text-sm text-gray-900 bg-gray-50 p-3 rounded">{{ $shipment->handling_notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>