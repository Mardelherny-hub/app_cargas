<div>
    <div class="relative" x-data="{ showShortcut: false }" x-init="
    // Detectar Ctrl+K para enfocar búsqueda
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            $refs.searchInput.focus();
            showShortcut = true;
            setTimeout(() => showShortcut = false, 2000);
        }
        // ESC para ocultar resultados
        if (e.key === 'Escape') {
            @this.hideResults();
            $refs.searchInput.blur();
        }
    })
">
    <!-- Campo de búsqueda principal -->
    <div class="relative">
        <div class="relative">
            <!-- Icono de búsqueda -->
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
            </div>
            
            <!-- Input de búsqueda -->
            <input 
                x-ref="searchInput"
                wire:model.live.debounce.300ms="query"
                type="text" 
                placeholder="Buscar BL, Contenedor, Cliente..."
                class="block w-full pl-10 pr-20 py-2 border border-gray-300 rounded-lg text-sm 
                       focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                       placeholder-gray-500 bg-white shadow-sm
                       transition-all duration-200 ease-in-out
                       focus:shadow-md hover:border-gray-400"
                autocomplete="off"
                @focus="$wire.showResults = ($wire.query.length >= 2)"
                @blur="setTimeout(() => $wire.hideResults(), 150)"
            >
            
            <!-- Shortcut hint y botón limpiar -->
            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                @if($query)
                    <button 
                        wire:click="clearSearch"
                        class="text-gray-400 hover:text-gray-600 transition-colors"
                        tabindex="-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                @else
                    <div class="hidden sm:block">
                        <kbd class="inline-flex items-center px-2 py-1 border border-gray-200 rounded text-xs font-medium bg-gray-50 text-gray-500">
                            <span class="text-xs">⌘</span>K
                        </kbd>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Tooltip de shortcut -->
        <div x-show="showShortcut" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             class="absolute top-full left-0 mt-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg shadow-lg z-50">
            ✨ Usa Ctrl+K para búsqueda rápida
        </div>
    </div>

    <!-- Dropdown de resultados -->
    @if($showResults && $this->results['total'] > 0)
        <div class="absolute top-full left-0 right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-xl z-50 max-h-96 overflow-hidden">
            <div class="max-h-96 overflow-y-auto">
                
                <!-- Bills of Lading -->
                @if($this->results['bills']->count() > 0)
                    <div class="p-3 border-b border-gray-100">
                        <div class="flex items-center mb-2">
                            <div class="w-5 h-5 bg-blue-100 rounded flex items-center justify-center mr-2">
                                <svg class="w-3 h-3 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Bills of Lading</span>
                        </div>
                        
                        @foreach($this->results['bills'] as $bill)
                            <button 
                                wire:click="goToResult('bill', {{ $bill->id }})"
                                class="w-full text-left p-2 hover:bg-gray-50 rounded-md transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 truncate">
                                            {!! $this->highlightMatch($bill->bill_number, $query) !!}
                                        </div>
                                        <div class="text-sm text-gray-500 truncate">
                                            {{ $bill->shipper->legal_name ?? 'Sin cargador' }} → {{ $bill->consignee->legal_name ?? 'Sin consignatario' }}
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            Viaje: {{ $bill->shipment->voyage->voyage_number ?? 'N/A' }}
                                        </div>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif

                <!-- Contenedores -->
                @if($this->results['containers']->count() > 0)
                    <div class="p-3 border-b border-gray-100">
                        <div class="flex items-center mb-2">
                            <div class="w-5 h-5 bg-green-100 rounded flex items-center justify-center mr-2">
                                <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2,4V6H4V18A2,2 0 0,0 6,20H18A2,2 0 0,0 20,18V6H22V4H2M6,6H18V18H6V6M8,8V16H10V8H8M14,8V16H16V8H14Z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Contenedores</span>
                        </div>
                        
                        @foreach($this->results['containers'] as $container)
                            <button 
                                wire:click="goToResult('container', {{ $container->id }})"
                                class="w-full text-left p-2 hover:bg-gray-50 rounded-md transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900">
                                            {!! $this->highlightMatch($container->container_number, $query) !!}
                                        </div>
                                        @if($container->shipmentItems->first())
                                            <div class="text-sm text-gray-500">
                                                BL: {{ $container->shipmentItems->first()->billOfLading->bill_number ?? 'N/A' }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif

                <!-- Clientes -->
                @if($this->results['clients']->count() > 0)
                    <div class="p-3 border-b border-gray-100">
                        <div class="flex items-center mb-2">
                            <div class="w-5 h-5 bg-purple-100 rounded flex items-center justify-center mr-2">
                                <svg class="w-3 h-3 text-purple-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Clientes</span>
                        </div>
                        
                        @foreach($this->results['clients'] as $client)
                            <button 
                                wire:click="goToResult('client', {{ $client->id }})"
                                class="w-full text-left p-2 hover:bg-gray-50 rounded-md transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 truncate">
                                            {!! $this->highlightMatch($client->legal_name, $query) !!}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {!! $this->highlightMatch($client->tax_id, $query) !!} - {{ $client->country->name ?? '' }}
                                        </div>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif

                <!-- Items de Carga -->
                @if($this->results['items']->count() > 0)
                    <div class="p-3 border-b border-gray-100">
                        <div class="flex items-center mb-2">
                            <div class="w-5 h-5 bg-orange-100 rounded flex items-center justify-center mr-2">
                                <svg class="w-3 h-3 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2,4V16H6V20A2,2 0 0,0 8,22H16A2,2 0 0,0 18,20V16H22V4H2M4,6H20V14H18V12A2,2 0 0,0 16,10H8A2,2 0 0,0 6,12V14H4V6M8,12H16V20H8V12Z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Items de Carga</span>
                        </div>
                        
                        @foreach($this->results['items'] as $item)
                            <button 
                                wire:click="goToResult('item', {{ $item->id }})"
                                class="w-full text-left p-2 hover:bg-gray-50 rounded-md transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 truncate">
                                            {!! $this->highlightMatch(Str::limit($item->item_description, 40), $query) !!}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            BL: {{ $item->billOfLading->bill_number ?? 'N/A' }}
                                            @if($item->commodity_code)
                                                - NCM: {{ $item->commodity_code }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif

                <!-- Voyages -->
                @if($this->results['voyages']->count() > 0)
                    <div class="p-3">
                        <div class="flex items-center mb-2">
                            <div class="w-5 h-5 bg-indigo-100 rounded flex items-center justify-center mr-2">
                                <svg class="w-3 h-3 text-indigo-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M3,14H7A1,1 0 0,1 8,15V17H16V15A1,1 0 0,1 17,14H21A1,1 0 0,1 22,15V18A2,2 0 0,1 20,20H4A2,2 0 0,1 2,18V15A1,1 0 0,1 3,14M5,4L7,6V12H9V6L11,4H13L15,6V12H17V6L19,4H21V2H3V4H5Z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Viajes</span>
                        </div>
                        
                        @foreach($this->results['voyages'] as $voyage)
                            <button 
                                wire:click="goToResult('voyage', {{ $voyage->id }})"
                                class="w-full text-left p-2 hover:bg-gray-50 rounded-md transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900">
                                            {!! $this->highlightMatch($voyage->voyage_number, $query) !!}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $voyage->originPort->name ?? 'N/A' }} → {{ $voyage->destinationPort->name ?? 'N/A' }}
                                        </div>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @elseif($showResults && $query && $this->results['total'] === 0)
        <!-- Sin resultados -->
        <div class="absolute top-full left-0 right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-xl z-50 p-4">
            <div class="text-center py-3">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Sin resultados</h3>
                <p class="mt-1 text-sm text-gray-500">
                    No se encontró nada con "{{ $query }}"
                </p>
            </div>
        </div>
    @endif
</div>
</div>
