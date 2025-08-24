<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📋 Historial de Importaciones
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Registro completo de archivos importados y sus resultados
                </p>
            </div>
            <div>
                <a href="{{ route('company.manifests.import.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    Nueva Importación
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filtros -->
            <div class="mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" 
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Buscar por nombre de archivo..." 
                                       id="searchInput">
                            </div>
                            <div>
                                <select class="block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="formatFilter">
                                    <option value="">Todos los formatos</option>
                                    <option value="kline">KLine (.dat)</option>
                                    <option value="parana">PARANA (.xlsx)</option>
                                    <option value="guaran">Guaraní (.csv)</option>
                                    <option value="login">Login (.xml)</option>
                                </select>
                            </div>
                            <div>
                                <select class="block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="statusFilter">
                                    <option value="">Todos los estados</option>
                                    <option value="completed">✅ Exitosas</option>
                                    <option value="completed_with_warnings">⚠️ Con advertencias</option>
                                    <option value="failed">❌ Fallidas</option>
                                    <option value="reverted">🔄 Revertidas</option>
                                </select>
                            </div>
                            <div>
                                <select class="block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" id="dateFilter">
                                    <option value="">Todo el tiempo</option>
                                    <option value="today">Hoy</option>
                                    <option value="week">Última semana</option>
                                    <option value="month">Último mes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de historial -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            Importaciones Recientes
                            @if($imports->total() > 0)
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $imports->total() }}
                                </span>
                            @endif
                        </h3>
                        @if($imports->hasPages())
                            <div class="text-sm text-gray-500">
                                Mostrando {{ $imports->firstItem() }}-{{ $imports->lastItem() }} de {{ $imports->total() }}
                            </div>
                        @endif
                    </div>
                </div>

                @if($imports->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Archivo
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Formato
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Usuario
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Resultados
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Viaje
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($imports as $import)
                                    <tr class="hover:bg-gray-50">
                                        <!-- Archivo -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="mr-4">
                                                    @switch($import->file_format)
                                                        @case('kline')
                                                            <svg class="h-8 w-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12z" clip-rule="evenodd"/>
                                                            </svg>
                                                            @break
                                                        @case('parana')
                                                            <svg class="h-8 w-8 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v8H4V6z" clip-rule="evenodd"/>
                                                            </svg>
                                                            @break
                                                        @case('guaran')
                                                            <svg class="h-8 w-8 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                                            </svg>
                                                            @break
                                                        @default
                                                            <svg class="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm12 2H4v8h12V6z" clip-rule="evenodd"/>
                                                            </svg>
                                                    @endswitch
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $import->file_name }}</div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ $import->file_size_formatted }}
                                                        @if($import->processing_time_seconds)
                                                            • {{ $import->processing_time_formatted }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Formato -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border">
                                                {{ strtoupper($import->file_format) }}
                                            </span>
                                        </td>

                                        <!-- Estado -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php $badge = $import->status_badge @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($badge['color'] === 'green') bg-green-100 text-green-800
                                                @elseif($badge['color'] === 'yellow') bg-yellow-100 text-yellow-800
                                                @elseif($badge['color'] === 'red') bg-red-100 text-red-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ $badge['text'] }}
                                            </span>
                                            @if($import->warnings_count > 0)
                                                <div class="text-yellow-600 text-xs mt-1">
                                                    <svg class="inline h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ $import->warnings_count }} advertencia{{ $import->warnings_count > 1 ? 's' : '' }}
                                                </div>
                                            @endif
                                            @if($import->errors_count > 0)
                                                <div class="text-red-600 text-xs mt-1">
                                                    <svg class="inline h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ $import->errors_count }} error{{ $import->errors_count > 1 ? 'es' : '' }}
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Fecha -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div>{{ $import->created_at->format('d/m/Y') }}</div>
                                            <div class="text-gray-500">{{ $import->created_at->format('H:i:s') }}</div>
                                        </td>

                                        <!-- Usuario -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-8 w-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs font-medium mr-3">
                                                    {{ strtoupper(substr($import->user->name ?? 'U', 0, 2)) }}
                                                </div>
                                                <div class="text-sm text-gray-900">{{ $import->user->name ?? 'Usuario' }}</div>
                                            </div>
                                        </td>

                                        <!-- Resultados -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($import->created_bills > 0)
                                                <div class="text-blue-600">
                                                    <svg class="inline h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v8H4V6z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ $import->created_bills }} BL{{ $import->created_bills > 1 ? 's' : '' }}
                                                </div>
                                            @endif
                                            @if($import->created_containers > 0)
                                                <div class="text-indigo-600">
                                                    <svg class="inline h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                                    </svg>
                                                    {{ $import->created_containers }} contenedor{{ $import->created_containers > 1 ? 'es' : '' }}
                                                </div>
                                            @endif
                                            @if($import->created_items > 0)
                                                <div class="text-green-600">
                                                    <svg class="inline h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                                                    </svg>
                                                    {{ $import->created_items }} items
                                                </div>
                                            @endif
                                            @if($import->created_clients > 0)
                                                <div class="text-yellow-600">
                                                    <svg class="inline h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                                                    </svg>
                                                    {{ $import->created_clients }} cliente{{ $import->created_clients > 1 ? 's' : '' }}
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Viaje -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($import->voyage)
                                                <a href="{{ route('company.voyages.show', $import->voyage) }}" 
                                                   class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                                    {{ $import->voyage->voyage_number }}
                                                    @if($import->voyage->originPort && $import->voyage->destinationPort)
                                                        <div class="text-gray-500 text-xs">
                                                            {{ $import->voyage->originPort->code }} → {{ $import->voyage->destinationPort->code }}
                                                        </div>
                                                    @endif
                                                </a>
                                            @else
                                                <span class="text-gray-400 text-sm">Sin viaje</span>
                                            @endif
                                        </td>

                                        <!-- Acciones -->
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="relative inline-block text-left">
                                                <div>
                                                    <button type="button" class="inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="menu-button-{{ $import->id }}" onclick="toggleDropdown({{ $import->id }})">
                                                        Acciones
                                                        <svg class="-mr-1 ml-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10" role="menu" id="dropdown-{{ $import->id }}">
                                                    <div class="py-1" role="none">
    <a href="{{ route('company.manifests.import.show', $import) }}" 
       class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
        <svg class="mr-3 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        Ver Detalles
    </a>

    @if($import->voyage)
        <a href="{{ route('company.voyages.show', $import->voyage) }}" 
           class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
            <svg class="mr-3 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/>
            </svg>
            Ver viaje
        </a>
    @endif

    @if($import->can_be_reverted && $import->voyage && $import->voyage->status === 'planning' && !$import->reverted_at)
        <div class="border-t border-gray-100"></div>
        <button type="button" 
                onclick="openRevertModal({{ $import->id }}, '{{ $import->file_name }}')"
                class="flex items-center w-full px-4 py-2 text-sm text-red-700 hover:bg-red-50" role="menuitem">
            <svg class="mr-3 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
            </svg>
            Revertir importación
        </button>
    @endif

    @if($import->reverted_at)
        <div class="border-t border-gray-100"></div>
        <div class="flex items-center px-4 py-2 text-sm text-gray-500">
            <svg class="mr-3 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            Revertida el {{ $import->reverted_at->format('d/m/Y H:i') }}
        </div>
    @endif
</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    @if($imports->hasPages())
                        <div class="px-6 py-4 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-500">
                                    Mostrando {{ $imports->firstItem() }}-{{ $imports->lastItem() }} de {{ $imports->total() }} importaciones
                                </div>
                                <div>
                                    {{ $imports->links() }}
                                </div>
                            </div>
                        </div>
                    @endif

                @else
                    <!-- Estado vacío -->
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 112 4m-8 9v2m0 0v2m0-2h2m-2 0H4"/>
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900">No hay importaciones aún</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Cuando importe archivos de manifiestos, aparecerán aquí con su historial completo.
                        </p>
                        <div class="mt-6">
                            <a href="{{ route('company.manifests.import.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Importar primer archivo
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($imports->count() > 0)
        <!-- Modal de confirmación para revertir -->
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" id="revertModal">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center mb-4">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 ml-4">
                            Revertir Importación
                        </h3>
                    </div>
                    <div class="mb-4">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        <strong>¡Atención!</strong> Esta acción eliminará permanentemente todos los datos creados por esta importación.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 mb-2">Se eliminarán:</p>
                        <ul class="text-sm text-gray-600 list-disc list-inside space-y-1 mb-4">
                            <li>Conocimientos de embarque (Bills of Lading)</li>
                            <li>Items de carga (Shipment Items)</li>
                            <li>Contenedores (si aplica)</li>
                            <li>El viaje creado</li>
                        </ul>
                        <p class="text-xs text-gray-500">
                            <strong>Nota:</strong> Los clientes y puertos se mantendrán ya que pueden estar siendo utilizados por otras importaciones.
                        </p>
                    </div>
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeRevertModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                            Cancelar
                        </button>
                        <form id="revertForm" method="POST" action="">
                            @csrf
                            <input type="hidden" name="reason" value="Reversión manual desde historial">
                            
                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="button" 
                                        onclick="closeRevertModal()"
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Cancelar
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                    Confirmar Reversión
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
        // Filtros en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const formatFilter = document.getElementById('formatFilter');
            const statusFilter = document.getElementById('statusFilter');
            const dateFilter = document.getElementById('dateFilter');
            
            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase();
                const formatValue = formatFilter.value;
                const statusValue = statusFilter.value;
                const dateValue = dateFilter.value;
                
                const rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const fileName = row.querySelector('td:first-child .text-sm')?.textContent.toLowerCase() || '';
                    const format = row.querySelector('td:nth-child(2) span')?.textContent.toLowerCase() || '';
                    const status = row.querySelector('td:nth-child(3) span')?.classList.toString() || '';
                    
                    let showRow = true;
                    
                    // Filtro de búsqueda
                    if (searchTerm && !fileName.includes(searchTerm)) {
                        showRow = false;
                    }
                    
                    // Filtro de formato
                    if (formatValue && !format.includes(formatValue)) {
                        showRow = false;
                    }
                    
                    // Filtro de estado
                    if (statusValue) {
                        const statusMap = {
                            'completed': 'bg-green-100',
                            'completed_with_warnings': 'bg-yellow-100',
                            'failed': 'bg-red-100',
                            'reverted': 'bg-gray-100'
                        };
                        if (!status.includes(statusMap[statusValue])) {
                            showRow = false;
                        }
                    }
                    
                    row.style.display = showRow ? '' : 'none';
                });
            }
            
            searchInput.addEventListener('input', applyFilters);
            formatFilter.addEventListener('change', applyFilters);
            statusFilter.addEventListener('change', applyFilters);
            dateFilter.addEventListener('change', applyFilters);
        });

        // Función para toggle dropdown
        function toggleDropdown(importId) {
            const dropdown = document.getElementById('dropdown-' + importId);
            dropdown.classList.toggle('hidden');
            
            // Cerrar otros dropdowns
            document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
                if (el.id !== 'dropdown-' + importId) {
                    el.classList.add('hidden');
                }
            });
        }

        // Cerrar dropdowns al hacer click fuera
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[id^="menu-button-"]')) {
                document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
                    el.classList.add('hidden');
                });
            }
        });

        // Función para confirmar reversión
        function confirmRevert(importId) {
            const modal = document.getElementById('revertModal');
            const form = document.getElementById('revertForm');
            form.action = `{{ route('company.manifests.import.index') }}/${importId}/revert`;
            modal.classList.remove('hidden');
        }

        // Función para cerrar modal de reversión
        function closeRevertModal() {
            const modal = document.getElementById('revertModal');
            modal.classList.add('hidden');
        }

        // Cerrar modal al hacer click fuera
        document.getElementById('revertModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeRevertModal();
            }
        });

        // Variables globales para el modal de reversión
        let currentImportId = null;

        // Abrir modal de reversión
        function openRevertModal(importId, fileName) {
            currentImportId = importId;
            
            // Actualizar el título del modal con el nombre del archivo
            const modalTitle = document.querySelector('#revertModal h3');
            if (modalTitle) {
                modalTitle.textContent = `Revertir: ${fileName}`;
            }
            
            // Configurar la acción del formulario
            const form = document.getElementById('revertForm');
            if (form) {
                form.action = `/company/manifests/import/${importId}/revert`;
            }
            
            // Mostrar modal
            document.getElementById('revertModal').classList.remove('hidden');
        }

        // Cerrar modal de reversión
        function closeRevertModal() {
            currentImportId = null;
            document.getElementById('revertModal').classList.add('hidden');
        }

        // Cerrar modal al hacer clic fuera de él
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('revertModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeRevertModal();
                    }
                });
            }
        });
    </script>
    @endpush
</x-app-layout>