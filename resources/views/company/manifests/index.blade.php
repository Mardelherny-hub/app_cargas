<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📋 Manifiestos
            </h2>
            <nav class="text-sm">
                <a href="{{ route('company.dashboard') }}" class="text-gray-500 hover:text-gray-700">
                    Dashboard
                </a>
                <span class="text-gray-400 mx-2">/</span>
                <span class="text-gray-900">Manifiestos</span>
            </nav>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Estadísticas rápidas -->
            @if($voyages->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <span class="text-white font-semibold">📋</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Manifiestos</dt>
                                    <dd class="text-lg font-medium text-gray-900">{{ $voyages->total() }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <span class="text-white font-semibold">✅</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Completados</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $voyages->where('status', 'completed')->count() }}
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
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <span class="text-white font-semibold">🔄</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">En Proceso</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        {{ $voyages->whereIn('status', ['planning', 'in_progress'])->count() }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Tabla de manifiestos -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Listado de Manifiestos
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Gestión de viajes y manifiestos de carga
                            </p>
                        </div>
                        
                        <div class="flex space-x-3">
                            <a href="{{ route('company.manifests.import.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                📊 Importar
                            </a>
                            <a href="{{ route('company.manifests.create') }}"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                ➕ Nuevo Manifiesto
                            </a>
                        </div>
                    </div>
                </div>

                @if($voyages->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    N° Viaje
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Origen
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Destino
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Embarques
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($voyages as $voyage)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $voyage->voyage_number }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        @if($voyage->originPort)
                                            <div class="font-medium">{{ $voyage->originPort->name }}</div>
                                            @if($voyage->originPort->code)
                                                <div class="text-xs text-gray-500">({{ $voyage->originPort->code }})</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">Puerto no especificado</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        @if($voyage->destinationPort)
                                            <div class="font-medium">{{ $voyage->destinationPort->name }}</div>
                                            @if($voyage->destinationPort->code)
                                                <div class="text-xs text-gray-500">({{ $voyage->destinationPort->code }})</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">Puerto no especificado</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusClasses = [
                                            'planning' => 'bg-yellow-100 text-yellow-800',
                                            'in_progress' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                        ];
                                        $statusLabels = [
                                            'planning' => 'Planificación',
                                            'in_progress' => 'En Progreso',
                                            'completed' => 'Completado',
                                            'cancelled' => 'Cancelado',
                                        ];
                                    @endphp
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusClasses[$voyage->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusLabels[$voyage->status] ?? ucfirst($voyage->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $voyage->shipments->count() ?? 0 }} embarque(s)
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <a href="{{ route('company.manifests.show', $voyage->id) }}" 
                                           class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded text-xs">
                                            👁️ Ver
                                        </a>
                                        
                                        @if($voyage->status === 'planning')
                                        <a href="{{ route('company.manifests.edit', $voyage->id) }}" 
                                           class="text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 px-2 py-1 rounded text-xs">
                                            ✏️ Editar
                                        </a>
                                        @endif

                                        @if($voyage->status === 'completed' && $voyage->shipments->count() > 0)
                                        <a href="{{ route('company.manifests.customs.index', ['voyage_id' => $voyage->id]) }}" 
                                           class="text-purple-600 hover:text-purple-900 bg-purple-50 hover:bg-purple-100 px-2 py-1 rounded text-xs">
                                            🏛️ Aduana
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    {{ $voyages->links() }}
                </div>
                @else
                <!-- Estado vacío -->
                <div class="text-center py-12">
                    <div class="mx-auto h-12 w-12 text-gray-400 mb-4">
                        📋
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay manifiestos</h3>
                    <p class="mt-1 text-sm text-gray-500">Comience creando su primer manifiesto de carga.</p>
                    <div class="mt-6 space-x-3">
                        <a href="{{ route('company.manifests.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                            ➕ Nuevo Manifiesto
                        </a>
                        <a href="{{ route('company.manifests.import.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700">
                            📊 Importar desde Excel
                        </a>
                    </div>
                </div>
                @endif
            </div>

            <!-- Información de ayuda -->
            @if($voyages->count() > 0)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Próximos pasos
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Los manifiestos en "Planificación" pueden editarse y agregar embarques</li>
                                <li>Los manifiestos "Completados" pueden enviarse a aduana</li>
                                <li>Use la importación masiva para procesar archivos Excel/CSV</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>