<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gestión de Clientes
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Base de datos compartida - {{ $stats['total'] ?? 0 }} clientes activos y verificados
                </p>
            </div>
            <div class="flex space-x-2">
                @can('create', App\Models\Client::class)
                    <a href="{{ route('admin.clients.create') }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nuevo Cliente
                    </a>
                @endcan
                <button type="button" 
                        onclick="toggleFilters()" 
                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filtros
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Estadísticas rápidas -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <!-- Total de Clientes -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Total Clientes
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ number_format($stats['total'] ?? 0) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verificados -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Verificados
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ number_format($stats['verified'] ?? 0) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CORRECCIÓN: Estadísticas por roles (múltiples) -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Cargadores
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ number_format($stats['shippers'] ?? 0) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Consignatarios
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ number_format($stats['consignees'] ?? 0) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recientes -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                        Últimos 30 días
                                    </dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ number_format($stats['recent'] ?? 0) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de filtros (colapsable) -->
            <div id="filters-panel" class="hidden bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4">
                    <form method="GET" action="{{ route('admin.clients.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Búsqueda -->
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                                <input type="text" 
                                       name="search" 
                                       id="search"
                                       value="{{ request('search') }}" 
                                       placeholder="CUIT/RUC, razón social..."
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>

                            <!-- CORRECCIÓN: Filtro por roles (múltiples) -->
                            <div>
                                <label for="client_role" class="block text-sm font-medium text-gray-700">Rol de Cliente</label>
                                <select name="client_role" 
                                        id="client_role"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Todos los roles</option>
                                    @foreach($availableRoles as $roleKey => $roleLabel)
                                        <option value="{{ $roleKey }}" {{ request('client_role') === $roleKey ? 'selected' : '' }}>
                                            {{ $roleLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- País -->
                            <div>
                                <label for="country_id" class="block text-sm font-medium text-gray-700">País</label>
                                <select name="country_id" 
                                        id="country_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Todos los países</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ request('country_id') == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Estado de verificación -->
                            <div>
                                <label for="verified" class="block text-sm font-medium text-gray-700">Verificación</label>
                                <select name="verified" 
                                        id="verified"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="yes" {{ request('verified') === 'yes' ? 'selected' : '' }}>
                                        Solo verificados
                                    </option>
                                    <option value="no" {{ request('verified') === 'no' ? 'selected' : '' }}>
                                        Sin verificar
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <div class="flex space-x-2">
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Aplicar Filtros
                                </button>
                                <a href="{{ route('admin.clients.index') }}" 
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                    Limpiar
                                </a>
                            </div>
                            <div class="text-sm text-gray-500">
                                Mostrando {{ $clients->count() }} de {{ $clients->total() }} resultados
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de Clientes -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <div class="px-4 py-5 sm:p-6">
                    @if($clients->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Cliente
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <!-- CORRECCIÓN: Cambio de "Tipo" a "Roles" -->
                                            Roles
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            País
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Contacto
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Creado Por
                                        </th>
                                        <th scope="col" class="relative px-4 py-3">
                                            <span class="sr-only">Acciones</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($clients as $client)
                                        <tr class="hover:bg-gray-50">
                                            <!-- Información del Cliente -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center">
                                                            <span class="text-sm font-medium text-white">
                                                                {{ strtoupper(substr($client->legal_name, 0, 2)) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            {{ $client->legal_name }}
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            {{ $client->tax_id }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- CORRECCIÓN: Mostrar múltiples roles -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex flex-wrap gap-1">
                                                    @forelse($client->client_roles ?? [] as $role)
                                                        @php
                                                            $roleColors = [
                                                                'shipper' => 'bg-purple-100 text-purple-800',
                                                                'consignee' => 'bg-orange-100 text-orange-800', 
                                                                'notify_party' => 'bg-blue-100 text-blue-800'
                                                            ];
                                                            $colorClass = $roleColors[$role] ?? 'bg-gray-100 text-gray-800';
                                                        @endphp
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                                                            {{ $availableRoles[$role] ?? $role }}
                                                        </span>
                                                    @empty
                                                        <span class="text-sm text-gray-500">Sin roles</span>
                                                    @endforelse
                                                </div>
                                            </td>

                                            <!-- País -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $client->country->name ?? 'No definido' }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $client->country->alpha2_code ?? '' }}
                                                </div>
                                            </td>

                                            <!-- Contacto -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                @if($client->primaryContact)
                                                    <div class="text-sm text-gray-900">
                                                        {{ $client->primaryContact->email ?: 'Sin email' }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ $client->primaryContact->phone ?: 'Sin teléfono' }}
                                                    </div>
                                                @else
                                                    <span class="text-sm text-gray-500">Sin contacto</span>
                                                @endif
                                            </td>

                                            <!-- Estado -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex flex-col space-y-1">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                        {{ $client->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ $client->status === 'active' ? 'Activo' : 'Inactivo' }}
                                                    </span>
                                                    @if($client->verified_at)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                            </svg>
                                                            Verificado
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            Pendiente
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>

                                            <!-- Creado Por -->
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $client->createdByCompany->legal_name ?? 'Sistema' }}
                                                </div>
                                                @if($client->created_at)
                                                    <div class="text-xs text-gray-500">
                                                        {{ $client->created_at->format('d/m/Y') }}
                                                    </div>
                                                @endif
                                            </td>
                                            
                                            <!-- Acciones -->
                                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-1">
                                                    <!-- Ver Detalles -->
                                                    @can('view', $client)
                                                        <a href="{{ route('admin.clients.show', $client) }}" 
                                                           class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                                                           title="Ver detalles">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                            </svg>
                                                        </a>
                                                    @endcan

                                                    <!-- Editar -->
                                                    @can('update', $client)
                                                        <a href="{{ route('admin.clients.edit', $client) }}" 
                                                           class="text-indigo-600 hover:text-indigo-900 p-1 rounded hover:bg-indigo-50"
                                                           title="Editar">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </a>
                                                    @endcan

                                                    <!-- Verificar -->
                                                    @if(!$client->verified_at && auth()->user()->hasRole('super-admin'))
                                                        <button onclick="verifyClient({{ $client->id }})"
                                                                class="text-green-600 hover:text-green-900 p-1 rounded hover:bg-green-50"
                                                                title="Verificar CUIT">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                        </button>
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
                            {{ $clients->appends(request()->query())->links() }}
                        </div>
                    @else
                        <!-- Estado vacío -->
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay clientes</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                No se encontraron clientes con los filtros aplicados.
                            </p>
                            @can('create', App\Models\Client::class)
                                <div class="mt-6">
                                    <a href="{{ route('admin.clients.create') }}" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Crear primer cliente
                                    </a>
                                </div>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleFilters() {
            const panel = document.getElementById('filters-panel');
            panel.classList.toggle('hidden');
        }

        function verifyClient(clientId) {
            if (confirm('¿Está seguro de que desea verificar este cliente?')) {
                fetch(`/admin/clients/${clientId}/verify`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al verificar cliente: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión');
                });
            }
        }
    </script>
</x-app-layout>