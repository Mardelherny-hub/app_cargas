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


    {{-- Mensajes Flash de Éxito/Error/Información --}}
    @if(session('success'))
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" 
                    class="fill-current h-6 w-6 text-green-500 cursor-pointer" 
                    role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Cerrar</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" 
                    class="fill-current h-6 w-6 text-red-500 cursor-pointer" 
                    role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Cerrar</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-6 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('warning') }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" 
                    class="fill-current h-6 w-6 text-yellow-500 cursor-pointer" 
                    role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Cerrar</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
    @endif

    @if(session('info'))
        <div class="mb-6 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('info') }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" 
                    class="fill-current h-6 w-6 text-blue-500 cursor-pointer" 
                    role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Cerrar</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
    @endif

    {{-- Errores de Validación --}}
    @if($errors->any())
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">¡Errores de validación!</strong>
            <ul class="mt-2 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.style.display='none'" 
                    class="fill-current h-6 w-6 text-red-500 cursor-pointer" 
                    role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Cerrar</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
    @endif

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


                    {{-- Gestión de Items de Carga - VERSIÓN MEJORADA --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Items de Carga</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Gestione los items y mercaderías de este shipment
                                    </p>
                                </div>
                                
                                @if($userPermissions['can_manage_items'])
                                    <div class="flex space-x-3">
                                        <a href="{{ route('company.shipment-items.create', ['shipment' => $shipment->id]) }}" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                            Agregar Item
                                        </a>
                                        
                                        @if($stats['total_items'] > 0)
                                            <button type="button" 
                                                    onclick="showBulkActionsModal()"
                                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                                Acciones Masivas
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            @if($stats['total_items'] > 0)
                                {{-- Estadísticas Rápidas de Items --}}
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                    <div class="bg-blue-50 rounded-lg p-3">
                                        <div class="text-sm font-medium text-blue-600">Total Items</div>
                                        <div class="text-2xl font-bold text-blue-900">{{ $stats['total_items'] }}</div>
                                    </div>
                                    <div class="bg-green-50 rounded-lg p-3">
                                        <div class="text-sm font-medium text-green-600">Bultos</div>
                                        <div class="text-2xl font-bold text-green-900">{{ number_format($stats['total_packages']) }}</div>
                                    </div>
                                    <div class="bg-purple-50 rounded-lg p-3">
                                        <div class="text-sm font-medium text-purple-600">Peso Bruto</div>
                                        <div class="text-2xl font-bold text-purple-900">{{ number_format($stats['total_gross_weight_kg'] / 1000, 1) }}t</div>
                                    </div>
                                    <div class="bg-yellow-50 rounded-lg p-3">
                                        <div class="text-sm font-medium text-yellow-600">Volumen</div>
                                        <div class="text-2xl font-bold text-yellow-900">{{ number_format($stats['total_volume_m3'], 1) }}m³</div>
                                    </div>
                                </div>

                                {{-- Alertas de Items --}}
                                @if($stats['dangerous_goods_count'] > 0 || $stats['items_requiring_permits'] > 0 || $stats['items_with_discrepancies'] > 0)
                                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-yellow-800">Atención Requerida</h3>
                                                <div class="mt-2 text-sm text-yellow-700">
                                                    <ul class="list-disc pl-5 space-y-1">
                                                        @if($stats['dangerous_goods_count'] > 0)
                                                            <li><strong>{{ $stats['dangerous_goods_count'] }}</strong> items con mercaderías peligrosas</li>
                                                        @endif
                                                        @if($stats['items_requiring_permits'] > 0)
                                                            <li><strong>{{ $stats['items_requiring_permits'] }}</strong> items requieren permisos especiales</li>
                                                        @endif
                                                        @if($stats['items_with_discrepancies'] > 0)
                                                            <li><strong>{{ $stats['items_with_discrepancies'] }}</strong> items con discrepancias</li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Tabla de Items Mejorada --}}
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                @if($userPermissions['can_manage_items'])
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        <input type="checkbox" id="select-all-items" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    </th>
                                                @endif
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Línea</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de Carga</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peso (kg)</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($shipment->shipmentItems as $item)
                                                <tr class="hover:bg-gray-50">
                                                    @if($userPermissions['can_manage_items'])
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <input type="checkbox" 
                                                                class="item-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                                                value="{{ $item->id }}">
                                                        </td>
                                                    @endif
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {{ $item->line_number }}
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-gray-900">
                                                        <div class="font-medium">{{ $item->item_description }}</div>
                                                        @if($item->commodity_description)
                                                            <div class="text-xs text-gray-500">{{ $item->commodity_description }}</div>
                                                        @endif
                                                        {{-- Indicadores especiales --}}
                                                        <div class="flex flex-wrap gap-1 mt-1">
                                                            @if($item->is_dangerous_goods)
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                    ⚠️ Peligroso
                                                                </span>
                                                            @endif
                                                            @if($item->requires_refrigeration)
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                    ❄️ Refrigerado
                                                                </span>
                                                            @endif
                                                            @if($item->is_fragile)
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                    📦 Frágil
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $item->cargoType->name ?? 'N/A' }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ number_format($item->package_quantity) }}
                                                        <div class="text-xs text-gray-500">{{ $item->packagingType->name ?? 'N/A' }}</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <div>Bruto: <strong>{{ number_format($item->gross_weight_kg) }}</strong></div>
                                                        <div class="text-xs text-gray-500">Neto: {{ number_format($item->net_weight_kg) }}</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                            @switch($item->status)
                                                                @case('draft') bg-gray-100 text-gray-800 @break
                                                                @case('validated') bg-blue-100 text-blue-800 @break
                                                                @case('submitted') bg-yellow-100 text-yellow-800 @break
                                                                @case('accepted') bg-green-100 text-green-800 @break
                                                                @case('rejected') bg-red-100 text-red-800 @break
                                                                @default bg-gray-100 text-gray-800
                                                            @endswitch">
                                                            @switch($item->status)
                                                                @case('draft') Borrador @break
                                                                @case('validated') Validado @break
                                                                @case('submitted') Enviado @break
                                                                @case('accepted') Aceptado @break
                                                                @case('rejected') Rechazado @break
                                                                @default {{ ucfirst($item->status) }}
                                                            @endswitch
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex space-x-2">
                                                            <a href="{{-- route('company.shipment-items.show', $item) --}}#" 
                                                            class="text-indigo-600 hover:text-indigo-900" title="Ver detalle">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                                </svg>
                                                            </a>
                                                            
                                                            @if($userPermissions['can_manage_items'])
                                                                <a href="{{ route('company.shipment-items.edit', $item) }}" 
                                                                class="text-yellow-600 hover:text-yellow-900" title="Editar">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                                    </svg>
                                                                </a>
                                                                
                                                                <button onclick="deleteItem({{ $item->id }}, '{{ $item->item_description }}')" 
                                                                        class="text-red-600 hover:text-red-900" title="Eliminar">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                    </svg>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                {{-- Estado Vacío Mejorado --}}
                                <div class="text-center py-12">
                                    <svg class="mx-auto h-24 w-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <h3 class="mt-4 text-lg font-medium text-gray-900">Carga sin items</h3>
                                    <p class="mt-2 text-gray-500">Este shipment aún no tiene items de carga agregados.</p>
                                    @if($userPermissions['can_manage_items'])
                                        <div class="mt-6">
                                            <a href="{{ route('company.shipment-items.create', ['shipment' => $shipment->id]) }}" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                </svg>
                                                Agregar Primer Item
                                            </a>
                                        </div>
                                    @else
                                        <p class="mt-4 text-sm text-gray-400">No tiene permisos para agregar items a este shipment.</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Gestión de Conocimientos de Embarque - NUEVA SECCIÓN --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Conocimientos de Embarque</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Gestione los conocimientos de embarque (B/L) de este shipment
                                    </p>
                                </div>
                                
                                {{-- Botón Crear Conocimiento - NUEVO --}}
                                @if($userPermissions['can_edit'] && in_array($shipment->status, ['loaded', 'in_transit', 'arrived']))                                    <div class="flex space-x-3">
                                        <a href="{{ route('company.bills-of-lading.create', ['shipment_id' => $shipment->id]) }}" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Crear Conocimiento
                                        </a>
                                    </div>
                                @else
                                    <div class="text-sm text-gray-500">
                                        @if(!in_array($shipment->status, ['loaded', 'in_transit', 'arrived']))
                                            El shipment debe estar cargado para crear conocimientos
                                        @else
                                            No tiene permisos para crear conocimientos
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Lista de Conocimientos Existentes --}}
                            @if($shipment->billsOfLading && $shipment->billsOfLading->count() > 0)
                                <div class="overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Número B/L
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Cargador → Consignatario
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Estado
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Fecha
                                                </th>
                                                <th scope="col" class="relative px-6 py-3">
                                                    <span class="sr-only">Acciones</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($shipment->billsOfLading as $bill)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <a href="{{ route('company.bills-of-lading.show', $bill) }}" 
                                                        class="text-indigo-600 hover:text-indigo-900">
                                                            {{ $bill->bill_number }}
                                                        </a>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <div class="flex items-center">
                                                            <span class="font-medium">{{ $bill->shipper->legal_name ?? 'N/A' }}</span>
                                                            <svg class="w-4 h-4 mx-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                                            </svg>
                                                            <span>{{ $bill->consignee->legal_name ?? 'N/A' }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                            @switch($bill->status)
                                                                @case('draft') bg-gray-100 text-gray-800 @break
                                                                @case('verified') bg-blue-100 text-blue-800 @break
                                                                @case('sent_to_customs') bg-yellow-100 text-yellow-800 @break
                                                                @case('accepted') bg-green-100 text-green-800 @break
                                                                @default bg-gray-100 text-gray-800
                                                            @endswitch">
                                                            @switch($bill->status)
                                                                @case('draft') Borrador @break
                                                                @case('verified') Verificado @break
                                                                @case('sent_to_customs') Enviado @break
                                                                @case('accepted') Aceptado @break
                                                                @default {{ ucfirst($bill->status) }}
                                                            @endswitch
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {{ $bill->bill_date ? $bill->bill_date->format('d/m/Y') : 'N/A' }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <a href="{{ route('company.bills-of-lading.show', $bill) }}" 
                                                        class="text-indigo-600 hover:text-indigo-900">
                                                            Ver
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                {{-- Sin Conocimientos --}}
                                <div class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay conocimientos de embarque</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        @if(in_array($shipment->status, ['loaded', 'in_transit', 'arrived']))
                                            Este shipment está listo para generar conocimientos de embarque.
                                        @else
                                            El shipment debe estar cargado para crear conocimientos.
                                        @endif
                                    </p>
                                    
                                    @if($userPermissions['can_manage_items'] && in_array($shipment->status, ['loaded', 'in_transit', 'arrived']))
                                        <div class="mt-6">
                                            <a href="{{ route('company.bills-of-lading.create', ['shipment_id' => $shipment->id]) }}" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                Crear Primer Conocimiento
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
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


{{-- Scripts para funcionalidad de items --}}
<script>
function deleteItem(itemId, itemDescription) {
    if (confirm(`¿Está seguro de eliminar el item "${itemDescription}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/company/shipment-items/${itemId}`;
        
        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';
        
        const tokenField = document.createElement('input');
        tokenField.type = 'hidden';
        tokenField.name = '_token';
        tokenField.value = '{{ csrf_token() }}';
        
        form.appendChild(methodField);
        form.appendChild(tokenField);
        document.body.appendChild(form);
        form.submit();
    }
}

function showBulkActionsModal() {
    const selectedItems = document.querySelectorAll('.item-checkbox:checked');
    if (selectedItems.length === 0) {
        alert('Debe seleccionar al menos un item para realizar acciones masivas.');
        return;
    }
    
    // TODO: Implementar modal de acciones masivas
    alert(`${selectedItems.length} items seleccionados. Funcionalidad de acciones masivas pendiente.`);
}

// Funcionalidad de seleccionar todos
document.getElementById('select-all-items')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});
</script>
</x-app-layout>

