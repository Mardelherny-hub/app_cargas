<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cockpit del Viaje') }} - {{ $voyage->voyage_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Encabezado del Viaje --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-3xl font-bold text-blue-600 mb-2">
                                🚢 Viaje {{ $voyage->voyage_number }}
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    {{ $voyageStatus['overall_status'] === 'ready' ? 'bg-green-100 text-green-800' : 
                                       ($voyageStatus['overall_status'] === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ ucfirst($voyage->status) }}
                                </span>
                            </h1>
                            <p class="text-gray-600 mb-1">
                                <strong>{{ $voyage->company->legal_name }}</strong> | 
                                {{ $voyage->originPort->name }} → {{ $voyage->destinationPort->name }}
                            </p>
                            <p class="text-gray-600">
                                Embarcación: {{ $voyage->leadVessel->name ?? 'N/A' }} | 
                                Capitán: {{ $voyage->captain->full_name ?? 'N/A' }}
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <a href="{{ route('company.voyages.edit', $voyage) }}" 
                               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                📝 Editar Viaje
                            </a>
                            <a href="{{ route('company.voyages.index') }}" 
                               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                ← Volver
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Resumen Ejecutivo --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Resumen Ejecutivo</h3>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $stats['total_shipments'] }}</div>
                            <div class="text-sm text-gray-600">Envíos</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-cyan-600">{{ $stats['total_bills_of_lading'] }}</div>
                            <div class="text-sm text-gray-600">Conocimientos</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ $stats['total_items'] }}</div>
                            <div class="text-sm text-gray-600">Ítems</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">{{ $stats['total_containers'] }}</div>
                            <div class="text-sm text-gray-600">Contenedores</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_weight_kg'] / 1000, 1) }}t</div>
                            <div class="text-sm text-gray-600">Peso Total</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold {{ $voyageStatus['completion_percentage'] === 100 ? 'text-green-600' : 'text-yellow-600' }}">
                                {{ $voyageStatus['completion_percentage'] }}%
                            </div>
                            <div class="text-sm text-gray-600">Completitud</div>
                        </div>
                    </div>
                    
                    {{-- Barras de Progreso --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Utilización de Carga</label>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $stats['capacity_utilization'] }}%"></div>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $stats['capacity_utilization'] }}% de {{ number_format($voyage->total_cargo_capacity_tons, 1) }}t</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Utilización de Contenedores</label>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-cyan-600 h-2 rounded-full" style="width: {{ $stats['container_utilization'] }}%"></div>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $stats['container_utilization'] }}% de {{ $voyage->total_container_capacity }} TEU</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                {{-- Estado del Viaje --}}
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">🎯 Estado del Viaje</h3>
                        
                        <div class="mb-4">
                            <p class="text-gray-700 mb-2">
                                <strong>{{ $voyageStatus['ready_shipments'] }} de {{ $voyageStatus['total_shipments'] }}</strong> envíos listos para aduanas
                            </p>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full {{ $voyageStatus['completion_percentage'] === 100 ? 'bg-green-600' : 'bg-yellow-500' }}" 
                                     style="width: {{ $voyageStatus['completion_percentage'] }}%"></div>
                            </div>
                            <div class="mt-2">
                                @if($voyageStatus['can_send_to_customs'])
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        ✅ Listo para enviar a aduanas
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        ⏳ Faltan verificar conocimientos
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            <p>Partida: {{ $voyage->departure_date?->format('d/m/Y') ?? 'No definida' }}</p>
                            <p>Llegada: {{ $voyage->estimated_arrival_date?->format('d/m/Y') ?? 'No definida' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Acciones Rápidas --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">⚡ Acciones Rápidas</h3>
                        
                        <div class="space-y-3">
                            @if($voyageStatus['can_send_to_customs'])
                                <a href="{{ route('company.manifests.customs', $voyage) }}" 
                                   class="w-full bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-center block">
                                    🏛️ Enviar a Aduanas
                                </a>
                            @endif
                            <a href="{{ route('company.shipments.create', ['voyage_id' => $voyage->id]) }}" 
                               class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center block">
                                ➕ Agregar Envío
                            </a>
                            <a href="{{ route('company.voyages.manifest-pdf', $voyage) }}" 
                               class="w-full bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-center block">
                                📋 Generar Manifiesto
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lista de Envíos --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">🚛 Envíos del Viaje</h3>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                            {{ count($shipmentData) }} envío(s)
                        </span>
                    </div>

                    @forelse($shipmentData as $data)
                        <div class="border-b border-gray-200 pb-6 mb-6 last:border-b-0 last:mb-0">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-4">
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium text-blue-600 mb-1">
                                        <a href="{{ route('company.shipments.show', $data['shipment']) }}" class="hover:underline">
                                            {{ $data['shipment']->shipment_number }}
                                        </a>
                                    </h4>
                                    <p class="text-gray-600">{{ $data['shipment']->vessel->name ?? 'Sin embarcación' }}</p>
                                </div>
                                
                                <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-6 mt-3 lg:mt-0">
                                    <div class="text-center sm:text-left">
                                        <div class="text-lg font-semibold">{{ $data['bills_count'] }}</div>
                                        <div class="text-sm text-gray-600">Conocimientos</div>
                                    </div>
                                    <div class="text-center sm:text-left">
                                        <div class="text-lg font-semibold">{{ $data['items_count'] }}</div>
                                        <div class="text-sm text-gray-600">Ítems</div>
                                    </div>
                                    <div class="min-w-24">
                                        <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                            <div class="h-2 rounded-full {{ $data['completion_percentage'] === 100 ? 'bg-green-600' : 'bg-yellow-500' }}" 
                                                 style="width: {{ $data['completion_percentage'] }}%"></div>
                                        </div>
                                        <div class="text-sm text-gray-600">{{ $data['completion_percentage'] }}% verificado</div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-wrap gap-2 mt-3 lg:mt-0">
                                    <a href="{{ route('company.shipments.show', $data['shipment']) }}" 
                                       class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded">
                                        👁️ Ver
                                    </a>
                                    <a href="{{ route('company.bills-of-lading.index', ['shipment_id' => $data['shipment']->id]) }}" 
                                       class="bg-cyan-500 hover:bg-cyan-700 text-white text-sm py-1 px-3 rounded">
                                        📋 BLs
                                    </a>
                                    @if(!$data['ready_for_customs'])
                                        <a href="{{ route('company.shipments.edit', $data['shipment']) }}" 
                                           class="bg-yellow-500 hover:bg-yellow-700 text-white text-sm py-1 px-3 rounded">
                                            ⚠️ Completar
                                        </a>
                                    @else
                                        <span class="bg-green-500 text-white text-sm py-1 px-3 rounded">✅ Listo</span>
                                    @endif
                                </div>
                            </div>
                            
                            {{-- Detalles de Conocimientos --}}
                            @if($data['bills_count'] > 0)
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($data['shipment']->billsOfLading->take(3) as $bl)
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <h5 class="font-medium text-gray-900 mb-1">
                                                <a href="{{ route('company.bills-of-lading.show', $bl) }}" class="hover:underline">
                                                    {{ $bl->bill_number }}
                                                </a>
                                                @if($bl->verified_at)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-1">✓</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 ml-1">⏳</span>
                                                @endif
                                            </h5>
                                            <p class="text-sm text-gray-600 mb-1">
                                                {{ $bl->shipper->legal_name ?? 'Sin cargador' }} → {{ $bl->consignee->legal_name ?? 'Sin consignatario' }}
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                {{ $bl->shipmentItems->count() }} ítem(s) | 
                                                {{ number_format($bl->shipmentItems->sum('gross_weight_kg'), 0) }} kg
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                                @if($data['bills_count'] > 3)
                                    <div class="mt-4">
                                        <a href="{{ route('company.bills-of-lading.index', ['shipment_id' => $data['shipment']->id]) }}" 
                                           class="text-blue-600 hover:underline text-sm">
                                            Ver todos los {{ $data['bills_count'] }} conocimientos →
                                        </a>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <p class="text-gray-600 mb-4">Este viaje aún no tiene envíos asignados</p>
                            <a href="{{ route('company.shipments.create', ['voyage_id' => $voyage->id]) }}" 
                               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                ➕ Crear Primer Envío
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>