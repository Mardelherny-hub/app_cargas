<div>
    <div>
    <div class="space-y-4"
     x-data="{ openDelete: false }"
     x-on:open-delete-confirmation.window="openDelete = true"
     x-on:toast.window="
        const { type, message } = $event.detail; 
        const el = document.getElementById('ctry-toast'); 
        el.className = type === 'success' ? 'bg-green-600 text-white px-3 py-2 rounded shadow-lg' : 'bg-red-600 text-white px-3 py-2 rounded shadow-lg';
        el.textContent = message; 
        el.style.display='inline-flex'; 
        setTimeout(()=>{ el.style.display='none' }, 3000);
     ">

    {{-- Toast --}}
    <div id="ctry-toast" 
         style="display:none;" 
         class="fixed top-4 right-4 z-50 flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg">
    </div>

    {{-- Filtros / Búsqueda simple pero inteligente --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Búsqueda inteligente
            </label>
            <div class="relative">
                <input
                    type="text"
                    placeholder="Argentina, AR, ARG, 032..."
                    class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                    wire:model.live.debounce.300ms="search"
                    autocomplete="off"
                />
                
                {{-- Icono de búsqueda --}}
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            
            <p class="mt-1 text-xs text-gray-500">
                Busca por nombre, código ISO, Alpha o numérico. Detecta automáticamente el tipo.
            </p>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Por página</label>
            <select
                class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                wire:model="perPage"
            >
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors" wire:click="sortBy('name')">
                        <div class="flex items-center gap-1">
                            Nombre
                            @if($sortField === 'name') 
                                <span class="text-indigo-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> 
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors hidden md:table-cell" wire:click="sortBy('official_name')">
                        <div class="flex items-center gap-1">
                            Nombre oficial
                            @if($sortField === 'official_name') 
                                <span class="text-indigo-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> 
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors" wire:click="sortBy('iso_code')">
                        <div class="flex items-center gap-1">
                            ISO
                            @if($sortField === 'iso_code') 
                                <span class="text-indigo-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> 
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors" wire:click="sortBy('alpha_code')">
                        <div class="flex items-center gap-1">
                            Alpha
                            @if($sortField === 'alpha_code') 
                                <span class="text-indigo-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> 
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 cursor-pointer hover:bg-gray-100 transition-colors hidden md:table-cell" wire:click="sortBy('numeric_code')">
                        <div class="flex items-center gap-1">
                            Numérico
                            @if($sortField === 'numeric_code') 
                                <span class="text-indigo-600">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> 
                            @endif
                        </div>
                    </th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($countries as $country)
                    <tr wire:key="country-{{ $country->id }}" class="text-sm hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $country->name }}</div>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell text-gray-600">{{ $country->official_name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $country->iso_code }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ $country->alpha_code }}
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            <span class="text-gray-500 font-mono text-xs">{{ $country->numeric_code }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.countries.edit', $country->id) }}"
                                   class="px-3 py-1 text-xs rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                                    Editar
                                </a>
                                <button
                                    type="button"
                                    class="px-3 py-1 text-xs rounded-md bg-red-600 text-white hover:bg-red-700 transition-colors"
                                    wire:click="confirmDelete({{ $country->id }})">
                                    Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <div class="text-sm text-gray-500">
                                    @if(strlen(trim($search)) > 0)
                                        No hay resultados para "<strong>{{ $search }}</strong>"
                                        <div class="mt-1">
                                            <button 
                                                type="button" 
                                                class="text-indigo-600 hover:text-indigo-800"
                                                wire:click="$set('search', '')"
                                            >
                                                Limpiar búsqueda
                                            </button>
                                        </div>
                                    @else
                                        No hay países disponibles
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación mejorada --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-sm text-gray-600">
            @if($countries->total() > 0)
                Mostrando {{ $countries->firstItem() }}–{{ $countries->lastItem() }} de {{ number_format($countries->total()) }}
                @if(strlen(trim($search)) > 0)
                    <span class="text-gray-400">• Filtrado</span>
                @endif
            @endif
        </div>
        <div>
            {{ $countries->onEachSide(1)->links() }}
        </div>
    </div>

    {{-- Modal confirmación de borrado --}}
    <div
        x-cloak
        x-show="openDelete"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
        x-transition
        aria-modal="true" role="dialog"
    >
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 m-4">
            <div class="flex items-center gap-4 mb-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Confirmar eliminación</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        ¿Seguro que deseas eliminar este país? Esta acción no se puede deshacer.
                    </p>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3">
                <button type="button" 
                        class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors" 
                        @click="openDelete = false">
                    Cancelar
                </button>
                <button type="button"
                        class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors"
                        @click="$wire.delete(); openDelete=false;">
                    Eliminar
                </button>
            </div>
        </div>
    </div>
</div>
</div>
