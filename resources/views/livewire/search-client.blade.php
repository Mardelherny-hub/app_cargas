<div>
    {{-- <input type="hidden" name="{{ $fieldName }}" value="{{ $selectedClientId }}"> --}}
    
    @if($selectedClientId)
        {{-- Cliente seleccionado --}}
        <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-md">
            <div>
                <p class="text-sm font-medium text-green-800">Cliente seleccionado:</p>
                <p class="text-sm text-green-700">{{ $selectedClientName }}</p>
            </div>
            <button type="button" wire:click="clearSelectedClient" class="text-green-600 hover:text-green-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @else
        {{-- Búsqueda de cliente --}}
        <div class="space-y-3">
            <div class="flex space-x-2">
                <div class="flex-1">
                    <input wire:model.live="searchClient" 
                           type="text" 
                           class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                           placeholder="{{ $placeholder }}">
                </div>
                <div class="flex items-end">
                    <a href="{{ route('company.clients.create') }}" 
                       target="_blank"
                       class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Crear
                    </a>
                </div>
            </div>
            
            {{-- Resultados de búsqueda --}}
            @if($searchClient && $this->filteredClients->count() > 0)
                <div class="border border-gray-200 rounded-md max-h-40 overflow-y-auto">
                    @foreach($this->filteredClients as $client)
                        <div wire:click="selectClient({{ $client->id }})" 
                             class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0">
                            <p class="text-sm font-medium text-gray-900">{{ $client->legal_name }}</p>
                            <p class="text-xs text-gray-500">{{ $client->tax_id }}</p>
                        </div>
                    @endforeach
                </div>
            @elseif($searchClient && strlen($searchClient) >= 2)
                <p class="text-sm text-gray-500 italic">No se encontraron clientes</p>
            @endif
        </div>
    @endif
</div>