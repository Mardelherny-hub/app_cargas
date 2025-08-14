<div>
    <div class="bg-white overflow-hidden shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">⚙️ Gestión Masiva de Estados</h3>
        
        <!-- Selector de entidad y búsqueda -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="entity-select" class="block text-sm font-medium text-gray-700">Entidad</label>
                <select wire:model.live="selectedEntity" id="entity-select" 
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="voyages">🚢 Viajes</option>
                    <option value="shipments">📦 Cargas</option>
                    <option value="bills_of_lading">📋 Bills of Lading</option>
                    <option value="shipment_items">📄 Items</option>
                </select>
            </div>
            
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                <input wire:model.live.debounce.300ms="search" type="text" id="search" 
                    placeholder="Buscar por número, referencia..."
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            
            <div class="flex items-end">
                @if(count($selectedItems) > 0)
                    <button wire:click="initiateBulkAction('update_status')" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Cambiar Estado ({{ count($selectedItems) }})
                    </button>
                @endif
            </div>
        </div>

        <!-- Tabla de elementos -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" wire:model.live="selectAll" 
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            @switch($selectedEntity)
                                @case('voyages') Viaje @break
                                @case('shipments') Carga @break  
                                @case('bills_of_lading') Bill of Lading @break
                                @case('shipment_items') Item @break
                            @endswitch
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Estado Actual
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Información
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Última Actualización
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Acción Individual
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" wire:model.live="selectedItems" value="{{ $item->id }}" 
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    @switch($selectedEntity)
                                        @case('voyages')
                                            {{ $item->voyage_number }}
                                            @break
                                        @case('shipments')
                                            {{ $item->shipment_number }}
                                            @break
                                        @case('bills_of_lading')
                                            {{ $item->bill_number }}
                                            @break
                                        @case('shipment_items')
                                            {{ $item->item_reference ?? 'Línea ' . $item->line_number }}
                                            @break
                                    @endswitch
                                </div>
                                <div class="text-sm text-gray-500">
                                    @switch($selectedEntity)
                                        @case('voyages')
                                            {{ $item->internal_reference }}
                                            @break
                                        @case('shipments')
                                            Viaje: {{ $item->voyage->voyage_number ?? 'N/A' }}
                                            @break
                                        @case('bills_of_lading')
                                            {{ $item->house_bill_number }}
                                            @break
                                        @case('shipment_items')
                                            BL: {{ $item->billOfLading->bill_number ?? 'N/A' }}
                                            @break
                                    @endswitch
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $this->getStatusColor($item->status) }}">
                                    {{ $availableStatuses[$item->status] ?? ucwords(str_replace('_', ' ', $item->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    @switch($selectedEntity)
                                        @case('voyages')
                                            <div>{{ $item->originPort->code ?? 'N/A' }} → {{ $item->destinationPort->code ?? 'N/A' }}</div>
                                            <div class="text-gray-500">{{ $item->leadVessel->name ?? 'Sin embarcación' }}</div>
                                            @break
                                        @case('shipments')
                                            <div>{{ $item->vessel->name ?? 'Sin embarcación' }}</div>
                                            <div class="text-gray-500">{{ $item->cargo_type ?? 'N/A' }}</div>
                                            @break
                                        @case('bills_of_lading')
                                            <div>{{ $item->shipper->legal_name ?? 'N/A' }}</div>
                                            <div class="text-gray-500">→ {{ $item->consignee->legal_name ?? 'N/A' }}</div>
                                            @break
                                        @case('shipment_items')
                                            <div>{{ Str::limit($item->item_description, 30) }}</div>
                                            <div class="text-gray-500">{{ $item->package_quantity }} bultos</div>
                                            @break
                                    @endswitch
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $item->updated_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @livewire('status-changer', [
                                    'model' => $item,
                                    'size' => 'small',
                                    'showAsDropdown' => true
                                ], key($selectedEntity . '-' . $item->id))
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay elementos</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    @if($search)
                                        No se encontraron resultados para "{{ $search }}"
                                    @else
                                        No hay {{ $selectedEntity }} para mostrar
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="mt-6">
            {{ $items->links() }}
        </div>
    </div>

    <!-- Modal para actualización masiva -->
    @if($showBulkModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50">
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                                        Actualización Masiva de Estados
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500">
                                            Cambiar el estado de {{ count($selectedItems) }} elementos seleccionados.
                                        </p>
                                    </div>

                                    <div class="mt-4 space-y-4">
                                        <!-- Selector de nuevo estado -->
                                        <div>
                                            <label for="newStatus" class="block text-sm font-medium text-gray-700">Nuevo Estado</label>
                                            <select wire:model="newStatus" id="newStatus" 
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                <option value="">Seleccionar estado...</option>
                                                @foreach($availableStatuses as $status => $label)
                                                    <option value="{{ $status }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('newStatus')
                                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- Razón opcional -->
                                        <div>
                                            <label for="reason" class="block text-sm font-medium text-gray-700">Razón (opcional)</label>
                                            <textarea wire:model="reason" id="reason" rows="3" 
                                                placeholder="Descripción del cambio..."
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button wire:click="executeBulkStatusUpdate" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed">
                                <span wire:loading.remove wire:target="executeBulkStatusUpdate">Actualizar</span>
                                <span wire:loading wire:target="executeBulkStatusUpdate">Actualizando...</span>
                            </button>
                            <button wire:click="cancelBulkAction" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Mensajes de éxito/error -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" 
             x-show="show" 
             x-transition 
             x-init="setTimeout(() => show = false, 5000)"
             class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" 
             x-show="show" 
             x-transition 
             x-init="setTimeout(() => show = false, 5000)"
             class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50">
            {{ session('error') }}
        </div>
    @endif
</div>
</div>
