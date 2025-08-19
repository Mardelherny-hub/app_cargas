<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Gestión de Embarcaciones') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Administre las embarcaciones de su empresa
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Botón Agregar -->
                <a href="{{ route('company.vessels.create') }}" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Nueva Embarcación
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Aviso cuando no hay propietarios de embarcaciones disponibles -->
            @if(session('warning') && session('info') && session('next_step'))
            <div class="mb-6">
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 3.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.19-1.458-1.517-2.625L8.485 3.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-yellow-800">
                                {{ session('warning') }}
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>{{ session('info') }}</p>
                            </div>
                            <div class="mt-4">
                                <div class="flex">
                                    <a href="{{ session('next_step.url') }}" 
                                    class="bg-yellow-50 text-yellow-800 border border-yellow-200 hover:bg-yellow-100 rounded-md px-3 py-2 text-sm font-medium inline-flex items-center">
                                        @if(session('next_step.icon') === 'plus')
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                        @endif
                                        {{ session('next_step.text') }}
                                    </a>
                                    <a href="{{ route('company.vessel-owners.index') }}" 
                                    class="ml-3 bg-white text-yellow-800 border border-yellow-300 hover:bg-gray-50 rounded-md px-3 py-2 text-sm font-medium inline-flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Ver Propietarios Existentes
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            <!-- Filtros de Búsqueda -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('company.vessels.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        
                        <!-- Búsqueda General -->
                        <div class="md:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                            <input type="text" 
                                   name="search" 
                                   id="search"
                                   value="{{ request('search') }}"
                                   placeholder="Nombre, IMO, propietario..."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Filtro por Tipo -->
                        <div>
                            <label for="vessel_type_id" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Embarcación</label>
                            <select name="vessel_type_id" id="vessel_type_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todos los tipos</option>
                                @foreach($vesselTypes as $id => $name)
                                    <option value="{{ $id }}" {{ request('vessel_type_id') == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Filtro por Propietario -->
                        <div>
                            <label for="vessel_owner_id" class="block text-sm font-medium text-gray-700 mb-1">Propietario</label>
                            <select name="vessel_owner_id" id="vessel_owner_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todos los propietarios</option>
                                @foreach($vesselOwners as $id => $name)
                                    <option value="{{ $id }}" {{ request('vessel_owner_id') == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Filtro por Estado -->
                        <div>
                            <label for="operational_status" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select name="operational_status" id="operational_status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todos los estados</option>
                                <option value="active" {{ request('operational_status') === 'active' ? 'selected' : '' }}>Activa</option>
                                <option value="inactive" {{ request('operational_status') === 'inactive' ? 'selected' : '' }}>Inactiva</option>
                                <option value="maintenance" {{ request('operational_status') === 'maintenance' ? 'selected' : '' }}>Mantenimiento</option>
                                <option value="dry_dock" {{ request('operational_status') === 'dry_dock' ? 'selected' : '' }}>Dique Seco</option>
                                <option value="under_repair" {{ request('operational_status') === 'under_repair' ? 'selected' : '' }}>En Reparación</option>
                                <option value="decommissioned" {{ request('operational_status') === 'decommissioned' ? 'selected' : '' }}>Descomisionada</option>
                            </select>
                        </div>

                        <!-- Botones -->
                        <div class="md:col-span-5 flex items-end justify-between">
                            <div class="flex space-x-2">
                                <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    Filtrar
                                </button>
                                
                                <a href="{{ route('company.vessels.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                    Limpiar
                                </a>
                            </div>

                            <!-- Ordenamiento -->
                            <div class="flex items-center space-x-2 text-sm">
                                <span class="text-gray-500">Ordenar por:</span>
                                <select name="sort_by" class="text-sm border-gray-300 rounded-md" onchange="this.form.submit()">
                                    <option value="name" {{ request('sort_by', 'name') === 'name' ? 'selected' : '' }}>Nombre</option>
                                    <option value="imo_number" {{ request('sort_by') === 'imo_number' ? 'selected' : '' }}>IMO</option>
                                    <option value="length_meters" {{ request('sort_by') === 'length_meters' ? 'selected' : '' }}>Longitud</option>
                                    <option value="gross_tonnage" {{ request('sort_by') === 'gross_tonnage' ? 'selected' : '' }}>Tonelaje</option>
                                    <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Fecha</option>
                                </select>
                                
                                <select name="sort_order" class="text-sm border-gray-300 rounded-md" onchange="this.form.submit()">
                                    <option value="asc" {{ request('sort_order', 'asc') === 'asc' ? 'selected' : '' }}>↑ Ascendente</option>
                                    <option value="desc" {{ request('sort_order') === 'desc' ? 'selected' : '' }}>↓ Descendente</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Embarcaciones -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($vessels->count() > 0)
                        
                        <!-- Información de resultados -->
                        <div class="mb-4 flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Mostrando {{ $vessels->firstItem() }} a {{ $vessels->lastItem() }} de {{ $vessels->total() }} embarcaciones
                            </div>
                        </div>

                        <!-- Tabla de Embarcaciones -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Embarcación
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            IMO
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Propietario
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Especificaciones
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($vessels as $vessel)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">{{ $vessel->name }}</div>
                                                        <div class="text-sm text-gray-500">
                                                            Creada: {{ $vessel->created_at->format('d/m/Y') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $vessel->imo_number ?: 'Sin IMO' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $vessel->vesselType->name ?? 'N/A' }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $vessel->vesselOwner->legal_name ?? 'N/A' }}</div>
                                                <div class="text-sm text-gray-500">{{ $vessel->vesselOwner->tax_id ?? '' }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    @if($vessel->length_meters)
                                                        {{ number_format($vessel->length_meters, 1) }}m
                                                    @endif
                                                    @if($vessel->gross_tonnage)
                                                        • {{ number_format($vessel->gross_tonnage) }}t
                                                    @endif
                                                </div>
                                                @if($vessel->container_capacity)
                                                    <div class="text-sm text-gray-500">
                                                        {{ $vessel->container_capacity }} contenedores
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @switch($vessel->operational_status)
                                                        @case('active')
                                                            bg-green-100 text-green-800
                                                            @break
                                                        @case('inactive')
                                                            bg-gray-100 text-gray-800
                                                            @break
                                                        @case('maintenance')
                                                            bg-yellow-100 text-yellow-800
                                                            @break
                                                        @case('dry_dock')
                                                            bg-red-100 text-red-800
                                                            @break
                                                        @case('under_repair')
                                                            bg-orange-100 text-orange-800
                                                            @break
                                                        @case('decommissioned')
                                                            bg-red-200 text-red-900
                                                            @break
                                                        @default
                                                            bg-gray-100 text-gray-800
                                                    @endswitch">
                                                    @switch($vessel->operational_status)
                                                        @case('active')
                                                            Activa
                                                            @break
                                                        @case('inactive')
                                                            Inactiva
                                                            @break
                                                        @case('maintenance')
                                                            Mantenimiento
                                                            @break
                                                        @case('dry_dock')
                                                            Dique Seco
                                                            @break
                                                        @case('under_repair')
                                                            En Reparación
                                                            @break
                                                        @case('decommissioned')
                                                            Descomisionada
                                                            @break
                                                        @default
                                                            {{ ucfirst($vessel->operational_status) }}
                                                    @endswitch
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <!-- Ver -->
                                                    <a href="{{ route('company.vessels.show', $vessel) }}" 
                                                       class="text-blue-600 hover:text-blue-900" title="Ver detalles">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </a>

                                                    <!-- Editar -->
                                                    @if(auth()->user()->hasRole('company-admin'))
                                                        <a href="{{ route('company.vessels.edit', $vessel) }}" 
                                                           class="text-yellow-600 hover:text-yellow-900" title="Editar">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </a>

                                                        <!-- Toggle Estado -->
                                                        <form action="{{ route('company.vessels.toggle-status', $vessel) }}"                                                               method="POST" class="inline" 
                                                              onsubmit="return confirm('¿Está seguro de cambiar el estado de esta embarcación?')">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" 
                                                                    class="text-gray-600 hover:text-gray-900" 
                                                                    title="{{ $vessel->operational_status === 'active' ? 'Desactivar' : 'Activar' }}">
                                                                @if($vessel->operational_status === 'active')
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                    </svg>
                                                                @else
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                    </svg>
                                                                @endif
                                                            </button>
                                                        </form>

                                                        <!-- Eliminar -->
                                                        <form action="{{ route('company.vessels.destroy', $vessel) }}" 
                                                              method="POST" class="inline" 
                                                              onsubmit="return confirm('¿Está seguro de eliminar esta embarcación? Esta acción no se puede deshacer.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" 
                                                                    class="text-red-600 hover:text-red-900" 
                                                                    title="Eliminar">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="mt-6">
                            {{ $vessels->links() }}
                        </div>

                    @else
                        <!-- Estado Vacío -->
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2-2h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H17M3 19h2.586a1 1 0 00.707-.293l5.414-5.414a1 1 0 01.707-.293H15"/>
                            </svg>
                            <h3 class="mt-4 text-sm font-medium text-gray-900">Sin Embarcaciones</h3>
                            <p class="mt-2 text-sm text-gray-500">
                                No se encontraron embarcaciones que coincidan con los filtros seleccionados.
                            </p>
                            <div class="mt-4">
                                <a href="{{ route('company.vessels.create') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Crear Primera Embarcación
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>