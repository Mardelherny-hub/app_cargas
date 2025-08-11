<div>
    {{-- Badge de Estado Actual --}}
    <div class="inline-flex items-center">
        @if($this->nextStatus)
            {{-- Badge clickeable si hay siguiente estado --}}
            <button 
                wire:click="initiateStatusChange"
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium transition-all duration-200 hover:scale-105 hover:shadow-md cursor-pointer {{ $this->statusColor }}"
                title="Click para cambiar a: {{ $this->getStatusLabelProperty() }}"
            >
                {{ $this->statusLabel }}
                <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        @else
            {{-- Badge no clickeable para estados finales --}}
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $this->statusColor }}">
                {{ $this->statusLabel }}
                @if(in_array($voyage->status, ['completed', 'cancelled']))
                    <svg class="ml-1 w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </span>
        @endif
    </div>

    {{-- Modal de Confirmación --}}
    @if($showConfirmation)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:click="closeConfirmation">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" wire:click.stop>
                {{-- Header --}}
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Cambiar Estado del Viaje
                        </h3>
                        <button wire:click="closeConfirmation" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Información del Cambio --}}
                    <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-blue-900">{{ $voyage->voyage_number }}</span>
                        </div>
                        <div class="mt-2 flex items-center space-x-2">
                            <span class="px-2 py-1 text-xs rounded {{ $this->statusColor }}">
                                {{ $this->statusLabel }}
                            </span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            @php
                                $nextStatusLabel = [
                                    'approved' => 'Aprobado',
                                    'in_transit' => 'En Tránsito', 
                                    'at_destination' => 'En Destino',
                                    'completed' => 'Completado'
                                ][$nextStatus] ?? $nextStatus;
                                
                                $nextStatusColor = [
                                    'approved' => 'bg-green-100 text-green-800',
                                    'in_transit' => 'bg-yellow-100 text-yellow-800',
                                    'at_destination' => 'bg-purple-100 text-purple-800', 
                                    'completed' => 'bg-green-100 text-green-800'
                                ][$nextStatus] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="px-2 py-1 text-xs rounded {{ $nextStatusColor }}">
                                {{ $nextStatusLabel }}
                            </span>
                        </div>
                    </div>

                    {{-- Shipments Afectados --}}
                    @if(!empty($affectedShipments))
                        <div class="mb-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">
                                Cargas que serán afectadas ({{ count($affectedShipments) }}):
                            </h4>
                            <div class="max-h-32 overflow-y-auto space-y-1">
                                @foreach($affectedShipments as $shipment)
                                    <div class="flex justify-between items-center text-xs p-2 bg-gray-50 rounded">
                                        <span class="font-medium">{{ $shipment['number'] }}</span>
                                        <span class="text-gray-500">{{ $shipment['vessel'] }}</span>
                                        <div class="flex items-center space-x-1">
                                            <span class="px-1 py-0.5 bg-gray-200 rounded text-gray-700">
                                                {{ ucfirst($shipment['current_status']) }}
                                            </span>
                                            @if($shipment['suggested_status'])
                                                <span class="text-gray-400">→</span>
                                                <span class="px-1 py-0.5 bg-blue-200 rounded text-blue-700">
                                                    {{ ucfirst($shipment['suggested_status']) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Botones de Acción --}}
                    <div class="flex flex-col space-y-2">
                        @if(!empty($affectedShipments))
                            {{-- Opción: Actualizar todo --}}
                            <button 
                                wire:click="confirmStatusChange(true)"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Actualizar viaje + todas las cargas
                            </button>
                            
                            {{-- Opción: Solo viaje --}}
                            <button 
                                wire:click="confirmStatusChange(false)"
                                class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                            >
                                Actualizar solo el viaje
                            </button>
                        @else
                            {{-- Solo viaje (no hay shipments) --}}
                            <button 
                                wire:click="confirmStatusChange(false)"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Confirmar cambio
                            </button>
                        @endif
                        
                        {{-- Cancelar --}}
                        <button 
                            wire:click="closeConfirmation"
                            class="w-full px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Loading State --}}
    <div wire:loading class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-4 rounded-lg shadow-lg">
            <div class="flex items-center space-x-2">
                <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-600">Actualizando...</span>
            </div>
        </div>
    </div>
</div>