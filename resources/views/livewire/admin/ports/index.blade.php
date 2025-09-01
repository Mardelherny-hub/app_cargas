<div>
    <div>
    <div class="space-y-4" x-data="{ openDelete:false }" 
     x-on:open-delete-confirmation.window="openDelete = true"
     x-on:toast.window="
        const { type, message } = $event.detail; 
        const el = document.getElementById('lw-toast'); 
        el.className = type === 'success' ? 'bg-green-600 text-white px-3 py-2 rounded' : 'bg-red-600 text-white px-3 py-2 rounded';
        el.textContent = message; 
        el.style.display='inline-flex'; 
        setTimeout(()=>{ el.style.display='none' }, 2500);
     ">

    {{-- Toast --}}
    <div id="lw-toast" style="display:none;"></div>

    {{-- Buscador Inteligente Mejorado --}}
    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            {{-- Campo de búsqueda principal --}}
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Búsqueda Inteligente
                    <span class="text-xs text-gray-500 ml-1">({{ number_format($ports->total()) }} puertos)</span>
                </label>
                <div class="relative">
                    <input
                        type="text"
                        placeholder="Buscar por nombre, código, ciudad, país..."
                        class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 pr-10"
                        wire:model.live.debounce.300ms="search"
                        autocomplete="off"
                    />
                    {{-- Ícono de búsqueda --}}
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
                
                {{-- Tipos de búsqueda --}}
                <div class="mt-2 flex flex-wrap gap-2">
                    <label class="inline-flex items-center">
                        <input type="radio" wire:model="searchType" value="smart" class="rounded-full">
                        <span class="ml-1 text-xs text-gray-600">Inteligente</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" wire:model="searchType" value="code" class="rounded-full">
                        <span class="ml-1 text-xs text-gray-600">Códigos</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" wire:model="searchType" value="exact" class="rounded-full">
                        <span class="ml-1 text-xs text-gray-600">Exacta</span>
                    </label>
                </div>

                {{-- Ayuda contextual --}}
                @if($search)
                    <div class="mt-1 text-xs text-gray-500">
                        @if($searchType === 'smart')
                            💡 Búsqueda automática: 
                            @if(preg_match('/^[A-Za-z0-9]{2,8}$/', trim($search)))
                                detectando código...
                            @elseif(preg_match('/^[A-Z][a-z]{3,}/', trim($search)))
                                priorizando países/ciudades...
                            @else
                                búsqueda general...
                            @endif
                        @elseif($searchType === 'code')
                            🏷️ Ejemplo: BUEBA, MAR, CORD
                        @elseif($searchType === 'exact')
                            🎯 Coincidencia exacta activada
                        @endif
                    </div>
                @else
                    <div class="mt-1 text-xs text-gray-500">
                        💡 Ejemplos: "BUEBA" (código), "Buenos Aires" (ciudad), "Argentina" (país), "-34.6,-58.3" (coordenadas)
                    </div>
                @endif
            </div>

            {{-- Filtros --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">País</label>
                <select
                    class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    wire:model="countryId"
                >
                    <option value="">Todos los países</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Puerto</label>
                <select
                    class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    wire:model="portType"
                >
                    <option value="">Todos los tipos</option>
                    @foreach($portTypes as $pt)
                        <option value="{{ $pt }}">{{ $pt }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Filtros activos --}}
        @if($search || $countryId || $portType)
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="text-xs text-gray-500">Filtros activos:</span>
                @if($search)
                    <span class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                        "{{ $search }}"
                        <button wire:click="$set('search', '')" class="ml-1 hover:text-blue-600">×</button>
                    </span>
                @endif
                @if($countryId)
                    @php $selectedCountry = $countries->firstWhere('id', $countryId) @endphp
                    <span class="inline-flex items-center px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
                        {{ $selectedCountry->name ?? 'País' }}
                        <button wire:click="$set('countryId', null)" class="ml-1 hover:text-green-600">×</button>
                    </span>
                @endif
                @if($portType)
                    <span class="inline-flex items-center px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">
                        {{ $portType }}
                        <button wire:click="$set('portType', '')" class="ml-1 hover:text-purple-600">×</button>
                    </span>
                @endif
            </div>
        @endif

        {{-- Configuración de resultados --}}
        <div class="mt-3 flex items-center justify-between">
            <div class="text-sm text-gray-600">
                Mostrando {{ $ports->firstItem() ?? 0 }}–{{ $ports->lastItem() ?? 0 }} de {{ number_format($ports->total()) }}
            </div>
            <div class="flex items-center space-x-2">
                <label class="text-xs text-gray-600">Por página:</label>
                <select wire:model="perPage" class="text-sm rounded border-gray-300">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Tabla Responsiva --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3 cursor-pointer hover:bg-gray-100" wire:click="sortBy('name')">
                            <div class="flex items-center">
                                Nombre
                                @if($sortField === 'name')
                                    <span class="ml-1 text-gray-900">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer hover:bg-gray-100 hidden md:table-cell" wire:click="sortBy('short_name')">
                            <div class="flex items-center">
                                Abreviación
                                @if($sortField === 'short_name')
                                    <span class="ml-1 text-gray-900">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer hover:bg-gray-100" wire:click="sortBy('code')">
                            <div class="flex items-center">
                                Código
                                @if($sortField === 'code')
                                    <span class="ml-1 text-gray-900">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer hover:bg-gray-100" wire:click="sortBy('city')">
                            <div class="flex items-center">
                                Ciudad
                                @if($sortField === 'city')
                                    <span class="ml-1 text-gray-900">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer hover:bg-gray-100 hidden lg:table-cell" wire:click="sortBy('port_type')">
                            <div class="flex items-center">
                                Tipo
                                @if($sortField === 'port_type')
                                    <span class="ml-1 text-gray-900">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="px-4 py-3">País/Región</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($ports as $port)
                        <tr wire:key="port-{{ $port->id }}" class="text-sm hover:bg-gray-50 transition-colors">
                            {{-- Nombre con información adicional --}}
                            <td class="px-4 py-3">
                                <div class="space-y-1">
                                    <div class="font-medium text-gray-900">{{ $port->name }}</div>
                                    @if($port->local_name && $port->local_name !== $port->name)
                                        <div class="text-xs text-gray-500">{{ $port->local_name }}</div>
                                    @endif
                                    @if($port->address)
                                        <div class="text-xs text-blue-600">{{ Str::limit($port->address, 30) }}</div>
                                    @endif
                                </div>
                            </td>

                            {{-- Short name --}}
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="font-mono text-gray-700">{{ $port->short_name }}</span>
                            </td>

                            {{-- Código --}}
                            <td class="px-4 py-3">
                                <span class="font-mono font-medium text-indigo-600 bg-indigo-50 px-2 py-1 rounded text-xs">
                                    {{ $port->code }}
                                </span>
                            </td>

                            {{-- Ciudad --}}
                            <td class="px-4 py-3">
                                <div>{{ $port->city }}</div>
                                @if($port->province_state)
                                    <div class="text-xs text-gray-500">{{ $port->province_state }}</div>
                                @endif
                            </td>

                            {{-- Tipo de puerto --}}
                            <td class="px-4 py-3 hidden lg:table-cell">
                                @if($port->port_type)
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                        {{ $port->port_type }}
                                    </span>
                                @endif
                            </td>

                            {{-- País y región --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-2">
                                    @if($port->country)
                                        <span class="font-medium text-gray-900">{{ $port->country->name }}</span>
                                    @endif
                                </div>
                                @if($port->latitude && $port->longitude)
                                    <div class="text-xs text-gray-400 font-mono">
                                        {{ number_format($port->latitude, 3) }}, {{ number_format($port->longitude, 3) }}
                                    </div>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end space-x-1">
                                    <a href="{{ route('admin.ports.edit', $port->id) }}"
                                       class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-indigo-600 text-white hover:bg-indigo-700 transition-colors"
                                       title="Editar puerto">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Editar
                                    </a>
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-red-600 text-white hover:bg-red-700 transition-colors"
                                        wire:click="confirmDelete({{ $port->id }})"
                                        title="Eliminar puerto">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center">
                                <div class="flex flex-col items-center space-y-2">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    <div class="text-sm text-gray-500">
                                        @if($search || $countryId || $portType)
                                            No se encontraron puertos que coincidan con los filtros aplicados.
                                        @else
                                            No hay puertos registrados en el sistema.
                                        @endif
                                    </div>
                                    @if($search || $countryId || $portType)
                                        <button 
                                            wire:click="$set('search', ''); $set('countryId', null); $set('portType', '')"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                                            Limpiar filtros
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Paginación mejorada --}}
    @if($ports->hasPages())
        <div class="flex items-center justify-between bg-white px-4 py-3 border border-gray-200 rounded-lg">
            <div class="flex-1 flex justify-between items-center">
                <div class="text-sm text-gray-700">
                    Mostrando 
                    <span class="font-medium">{{ $ports->firstItem() ?? 0 }}</span>
                    a 
                    <span class="font-medium">{{ $ports->lastItem() ?? 0 }}</span>
                    de 
                    <span class="font-medium">{{ number_format($ports->total()) }}</span>
                    resultados
                </div>
                <div>
                    {{ $ports->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de confirmación de eliminación --}}
    <div
        x-cloak
        x-show="openDelete"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        aria-modal="true" 
        role="dialog"
        aria-labelledby="modal-title"
    >
        <div 
            class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 transform"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-6 py-4">
                <h3 id="modal-title" class="text-lg font-medium text-gray-900 text-center mb-2">
                    Confirmar Eliminación
                </h3>
                <p class="text-sm text-gray-600 text-center mb-4">
                    ¿Está seguro que desea eliminar este puerto permanentemente? 
                    <br>
                    <span class="font-medium text-red-600">Esta acción no se puede deshacer.</span>
                </p>
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                    <div class="flex">
                        <svg class="w-4 h-4 text-yellow-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <p class="text-xs text-yellow-700">
                            Si este puerto está referenciado en viajes o manifiestos, la eliminación será rechazada.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex items-center justify-end space-x-3">
                <button 
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                    @click="openDelete = false">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancelar
                </button>
                <button 
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                    @click="$wire.dispatch('delete-confirmed'); openDelete=false;">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Eliminar Puerto
                </button>
            </div>
        </div>
    </div>

    {{-- Botón flotante para crear nuevo puerto (opcional) --}}
    <div class="fixed bottom-6 right-6">
        <a href="{{ route('admin.ports.create') }}" 
           class="inline-flex items-center px-4 py-3 bg-indigo-600 border border-transparent rounded-full shadow-lg text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all hover:shadow-xl"
           title="Agregar nuevo puerto">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span class="hidden sm:block">Nuevo Puerto</span>
        </a>
    </div>

</div>
</div>
