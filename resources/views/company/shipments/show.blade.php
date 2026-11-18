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

    {{-- Mensajes Flash --}}
    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" 
                    class="fill-current h-6 w-6 text-green-500 cursor-pointer" 
                    role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg onclick="this.parentElement.parentElement.style.display='none'" 
                    class="fill-current h-6 w-6 text-red-500 cursor-pointer" 
                    role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
    @endif

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Planificación con Estado y Botones --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-6 py-4">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <h3 class="text-lg font-medium text-gray-900">Planificación</h3>
                            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full 
                                @switch($shipment->status)
                                    @case('planning') bg-gray-100 text-gray-800 @break
                                    @case('loading') bg-yellow-100 text-yellow-800 @break
                                    @case('loaded') bg-blue-100 text-blue-800 @break
                                    @case('in_transit') bg-indigo-100 text-indigo-800 @break
                                    @case('completed') bg-green-100 text-green-800 @break
                                    @default bg-gray-100 text-gray-800
                                @endswitch
                            ">
                                @switch($shipment->status)
                                    @case('planning') Planificación @break
                                    @case('loading') Cargando @break
                                    @case('loaded') Cargado @break
                                    @case('in_transit') En Tránsito @break
                                    @case('completed') Completado @break
                                    @default {{ ucfirst($shipment->status) }}
                                @endswitch
                            </span>

                            @if($shipment->requires_attention)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    ⚠️ Requiere Atención
                                </span>
                            @endif
                        </div>

                        <div class="flex space-x-3">
                            <a href="{{ route('company.shipment-items.create', ['shipment' => $shipment->id]) }}" 
                               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Iniciar Carga
                            </a>
                            <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-sm">
                                Marcar como Cargado
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Estadísticas Compactas --}}
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-500 rounded-lg p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold">{{ $stats['total_items'] }}</div>
                            <div class="text-sm opacity-90">Total Items</div>
                        </div>
                        <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-green-500 rounded-lg p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold">{{ number_format($stats['total_packages'], 0, ',', '.') }}</div>
                            <div class="text-sm opacity-90">Total Bultos</div>
                        </div>
                        <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-purple-500 rounded-lg p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold">{{ number_format($stats['total_gross_weight_kg'] / 1000, 1, ',', '.') }} t</div>
                            <div class="text-sm opacity-90">Peso Bruto</div>
                        </div>
                        <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-1m-3 1l-3-1"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-indigo-500 rounded-lg p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold">{{ ($stats['cargo_utilization_percentage'] ?? null) !== null ? ($stats['cargo_utilization_percentage'] == 0 ? '0%' : number_format($stats['cargo_utilization_percentage'], 1, ',', '.') . '%') : '—' }}</div>
                            @if(($stats['cargo_utilization_percentage'] ?? null) > 100)
                                <span class="bg-red-600 text-white rounded px-2 py-1 text-xs">Excede capacidad</span>
                            @endif
                            <div class="text-sm opacity-90">Utilización</div>
                        </div>
                        <svg class="w-8 h-8 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Layout Principal en 2 Columnas --}}
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                
                {{-- Columna Principal (3/4) --}}
                <div class="lg:col-span-3 space-y-6">
                    
                    {{-- Información del Viaje y Embarcación - Compacta --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información de Viaje y Embarcación</h3>
                            
                            <div class="grid grid-cols-2 gap-6">
                                {{-- Viaje --}}
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Viaje</h4>
                                    <div class="space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Número:</span>
                                            <a href="{{ route('company.voyages.show', $shipment->voyage) }}" 
                                               class="text-xs text-blue-600 hover:text-blue-500">
                                                {{ $shipment->voyage->voyage_number }}
                                            </a>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Ruta:</span>
                                            <span class="text-xs text-gray-900">{{ $shipment->voyage->originPort?->name ?? '—' }} → {{ $shipment->voyage->destinationPort?->name ?? '—' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Salida:</span>
                                            <span class="text-xs text-gray-900">{{ $shipment->voyage->departure_date?->format('d/m/Y H:i') ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Embarcación --}}
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Embarcación</h4>
                                    <div class="space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Nombre:</span>
                                            <span class="text-xs text-gray-900">{{ $shipment->vessel->name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Tipo:</span>
                                            <span class="text-xs text-gray-900">{{ $shipment->vessel->vesselType?->name ?? '—' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Propietario:</span>
                                            <span class="text-xs text-gray-900">{{ $shipment->vessel->owner?->legal_name ?? '—' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Rol:</span>
                                            <span class="text-xs text-gray-900">{{ ['single' => 'Embarcación Única', 'lead' => 'Líder', 'towed' => 'Remolcada', 'pushed' => 'Empujada', 'escort' => 'Escolta'][$shipment->vessel_role] ?? '—' }}
                                                @if($shipment->is_lead_vessel && $shipment->vessel_role !== 'lead')<span class="ml-1 text-blue-600">Líder</span>@endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Capitán - Compacto --}}
                            @if($shipment->captain)
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Capitán</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Nombre:</span>
                                            <span class="text-xs text-gray-900">{{ $shipment->captain->full_name ?? '—' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Licencia:</span>
                                            <span class="text-xs text-gray-900">
                                                {{ $shipment->captain->license_number ?? '—' }}
                                                <span class="ml-1 text-xs">{{ ['valid' => 'Vigente', 'expired' => 'Vencida', 'suspended' => 'Suspendida', 'revoked' => 'Revocada', 'pending_renewal' => 'Renovación pendiente'][$shipment->captain->license_status] ?? '—' }}</span>
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Experiencia:</span>
                                            <span class="text-xs text-gray-900">{{ $shipment->captain->years_of_experience !== null ? $shipment->captain->years_of_experience . ' años' : '—' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-xs text-gray-500">Contacto:</span>
                                            <span class="text-xs text-gray-900">{{ $shipment->captain->phone ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Capacidades - Barras de Progreso --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Capacidades y Utilización</h3>
                            
                            <div class="grid grid-cols-2 gap-6">
                                {{-- Carga --}}
                                <div>
                                    <div class="flex justify-between text-sm mb-2">
                                        <span>Cargado / Capacidad</span>
                                        <span>{{ $shipment->cargo_weight_loaded !== null ? number_format($shipment->cargo_weight_loaded / 1000, 1, ',', '.') . ' t' : '—' }} / {{ $shipment->cargo_capacity_tons !== null ? number_format($shipment->cargo_capacity_tons, 1, ',', '.') . ' t' : '—' }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-blue-600 h-3 rounded-full" style="width: {{ max(0, min(100, $stats['cargo_utilization_percentage'] ?? 0)) }}%"></div>
                                    </div>
                                    <div class="text-xs mt-1 {{ ($stats['cargo_utilization_percentage'] ?? null) > 100 ? 'text-red-600' : 'text-gray-500' }}">{{ ($stats['cargo_utilization_percentage'] ?? null) !== null ? number_format($stats['cargo_utilization_percentage'], 1, ',', '.') . '% utilizado' : '—' }}</div>
                                </div>

                                {{-- Contenedores --}}
                                <div>
                                    <div class="flex justify-between text-sm mb-2">
                                        <span>Cargados / Capacidad</span>
                                        <span>{{ $shipment->containers_loaded ?? '—' }} / {{ $shipment->container_capacity ?? '—' }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-green-600 h-3 rounded-full" style="width: {{ min(100, ($shipment->containers_loaded !== null && $shipment->container_capacity !== null) ? ($stats['container_utilization_percentage'] ?? 0) : 0) }}%"></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">{{ $shipment->containers_loaded !== null && $shipment->container_capacity !== null && ($stats['container_utilization_percentage'] ?? null) !== null ? number_format($stats['container_utilization_percentage'], 1, ',', '.') . '% utilizado' : '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Items de Carga - Optimizada --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Items de Carga</h3>
                                <div class="flex space-x-3">
                                    <a href="{{ route('company.shipment-items.create', ['shipment' => $shipment->id]) }}" 
                                       class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-sm">
                                        + Agregar Item
                                    </a>
                                    @if($stats['total_items'] > 0)
                                        <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-sm">
                                            ⚙️ Acciones Masivas
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if($stats['total_items'] > 0)
                                <div class="flex items-center p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <svg class="w-8 h-8 text-blue-400 mr-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-blue-900">
                                            Este shipment tiene <strong>{{ $stats['total_items'] }} {{ $stats['total_items'] == 1 ? 'item' : 'items' }}</strong> de carga registrados.
                                        </p>
                                        <p class="text-xs text-blue-700 mt-1">
                                            Para ver o gestionar los items, accedé al conocimiento (BL) correspondiente.
                                        </p>
                                    </div>
                                </div>
                            @else
                                {{-- Estado Vacío --}}
                                <div class="text-center py-8">
                                    <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <h3 class="mt-2 text-lg font-medium text-gray-900">El shipment debe estar cargado para crear conocimientos</h3>
                                    <p class="mt-1 text-gray-500">Este shipment aún no tiene items de carga agregados.</p>
                                    <div class="mt-4">
                                        <a href="{{ route('company.shipment-items.create', ['shipment' => $shipment->id]) }}" 
                                           class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                                            Agregar Primer Item
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Conocimientos de Embarque - Compacta --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Conocimientos de Embarque</h3>
                                <div class="flex space-x-2">
                                    <a href="{{ route('company.bills-of-lading.create', ['shipment_id' => $shipment->id]) }}" 
                                    class="inline-flex items-center mr-2 px-3 py-2 border border-green-300 shadow-sm text-sm leading-4 font-medium rounded-md text-green-700 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Nuevo Conocimiento
                                    </a>
                                </div>
                                <div class="text-sm text-gray-500 ml-2">
                                    Gestione los conocimientos de embarque de esta carga
                                </div>
                            </div>

                            {{-- Lista de Conocimientos --}}
                            @if($shipment->billsOfLading && $shipment->billsOfLading->count() > 0)
                            <div class="space-y-3">
                            @foreach($shipment->billsOfLading->take(10) as $bill)
                            <div class="flex items-center justify-between p-3 border rounded-lg">
                            <div class="flex-1">
                            <div class="font-medium text-sm">{{ $bill->bill_number ?? '—' }}</div>
                            <div class="text-xs text-gray-500">
                            {{ $bill->shipper->legal_name ?? '—' }} →
                            {{ $bill->consignee->legal_name ?? '—' }}
                            </div>
                            </div>
                            <div class="flex items-center space-x-4">
                            <div class="flex space-x-2">
                            {{-- Enlace Ver --}}
                            <a href="{{ route('company.bills-of-lading.show', $bill) }}" 
                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                            title="Ver conocimiento">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Ver
                            </a>
                            {{-- Enlace Editar --}}
                            <a href="{{ route('company.bills-of-lading.edit', $bill) }}" 
                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-orange-600 bg-orange-50 border border-orange-200 rounded hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1"
                            title="Editar conocimiento">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Editar
                            </a>
                            </div>
                            </div>
                            </div>
                            @endforeach
                            </div>
                            <a href="{{ route('company.bills-of-lading.index', ['shipment_id' => $shipment->id]) }}" class="inline-block mt-3 text-sm text-blue-600 hover:text-blue-500">Ver los {{ $shipment->billsOfLading->count() }} conocimientos</a>
                            @else
                            <div class="text-center py-6">
                            <div class="text-sm text-gray-500 mb-2">El shipment debe estar cargado para crear conocimientos</div>
                            </div>
                            @endif
                        </div>
                    </div>
                    {{-- PRÓXIMAS ACCIONES DEL SHIPMENT - Agregar después de la lista de BLs --}}
                    @if($shipment->billsOfLading && $shipment->billsOfLading->count() > 0)
                    <div class="mt-6 bg-white shadow rounded-lg">
                        <div class="px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">🚢 Próximas Acciones del Envío</h3>
                            
                            <div class="flex flex-wrap gap-3 mb-4">
                                @php
                                    $draftBLs = $shipment->billsOfLading->where('status', 'draft')->count();
                                    $verifiedBLs = $shipment->billsOfLading->where('status', 'verified')->count();
                                    $totalBLs = $shipment->billsOfLading->count();
                                    $allVerified = $draftBLs === 0 && $verifiedBLs > 0;
                                @endphp
                                
                                @if($draftBLs > 0)
                                    <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm">
                                        {{ $draftBLs }} BL(s) pendientes
                                    </span>
                                @endif
                                
                                @if($verifiedBLs > 0)
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                        {{ $verifiedBLs }} BL(s) verificados
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-3">
                                {{-- IR AL SISTEMA DE ADUANAS --}}
                                <a href="{{ route('company.simple.dashboard') }}"
                                class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 text-sm font-medium">
                                    🏛️ Enviar a Aduanas
                                </a>

                                {{-- Otras acciones --}}
                                <a href="{{ route('company.bills-of-lading.index', ['shipment_id' => $shipment->id]) }}" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                    📋 Ver BLs
                                </a>
                            </div>

                            {{-- Resumen --}}
                            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-700">
                                    <strong>{{ $totalBLs }} conocimiento(s)</strong> | 
                                    Items: {{ $shipment->billsOfLading->sum(function($bl) { return $bl->shipmentItems->count(); }) }}
                                    @if($allVerified)
                                        <br><span class="text-green-600 font-medium">✅ Listo para aduanas</span>
                                    @else
                                        <br><span class="text-yellow-600 font-medium">⏳ Verificar BLs pendientes</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                

                {{-- Sidebar Derecha (1/4) --}}
                <div class="space-y-6">
                    
                    {{-- Estadísticas Adicionales --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Estadísticas Adicionales</h3>
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Mercancías Peligrosas</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['dangerous_goods_count'] ?? '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Mercancías Perecederas</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['perishable_goods_count'] ?? '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Requieren Refrigeración</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['refrigerated_goods_count'] ?? '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Conocimientos de Embarque</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['total_bills_of_lading'] ?? '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Items con Discrepancias</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['items_with_discrepancies'] ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- Estado de Aprobaciones --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Estado de Aprobaciones</h3>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Seguridad Aprobada</span>
                                    <span class="text-sm text-gray-900">{{ $shipment->safety_approved === null ? '—' : ($shipment->safety_approved ? 'Sí' : 'No') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Aduana Liberada</span>
                                    <span class="text-sm text-gray-900">{{ $shipment->customs_cleared === null ? '—' : ($shipment->customs_cleared ? 'Sí' : 'No') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Documentación Completa</span>
                                    <span class="text-sm text-gray-900">{{ $shipment->documentation_complete === null ? '—' : ($shipment->documentation_complete ? 'Sí' : 'No') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Carga Inspeccionada</span>
                                    <span class="text-sm text-gray-900">{{ $shipment->cargo_inspected === null ? '—' : ($shipment->cargo_inspected ? 'Sí' : 'No') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Acciones</h3>
                            <div class="space-y-2">
                                @if($shipment->status === 'planning' && auth()->user()->hasRole('company-admin'))
                                    <form method="POST" action="{{ route('company.shipments.destroy', $shipment) }}" 
                                        class="inline w-full" 
                                        onsubmit="return confirm('¿Está seguro de eliminar esta carga? Esta acción no se puede deshacer.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="w-full bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-3 rounded text-sm">
                                            Eliminar Carga
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Scripts --}}
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
    </script>
</x-app-layout>