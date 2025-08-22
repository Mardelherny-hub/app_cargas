<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📊 Reporte de Importación
            </h2>
            <nav class="text-sm">
                <a href="{{ route('company.manifests.index') }}" class="text-gray-500 hover:text-gray-700">
                    Manifiestos
                </a>
                <span class="text-gray-400 mx-2">/</span>
                <a href="{{ route('company.manifests.import.index') }}" class="text-gray-500 hover:text-gray-700">
                    Importar
                </a>
                <span class="text-gray-400 mx-2">/</span>
                <span class="text-gray-900">Reporte</span>
            </nav>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Encabezado del reporte -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold mb-2">
                            @if($importResult['success'])
                                ✅ Importación Completada Exitosamente
                            @elseif($importResult['hasWarnings'])
                                ⚠️ Importación Completada con Advertencias
                            @else
                                ❌ Importación Falló
                            @endif
                        </h3>
                        <p class="text-green-100">
                            Archivo: <strong>{{ $fileInfo['name'] }}</strong>
                        </p>
                        <p class="text-green-100 text-sm">
                            Importado el {{ $fileInfo['imported_at'] }} por {{ $fileInfo['imported_by'] }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold">{{ $stats['processed_items'] ?? 0 }}</div>
                        <div class="text-sm text-green-100">Items procesados</div>
                    </div>
                </div>
            </div>

            <!-- Resumen estadístico -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Viaje creado -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                🚢
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Viaje</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                {{ $stats['voyage_created'] ? '1' : '0' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Shipments -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                📦
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Embarques</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                {{ $stats['shipments_count'] ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Bills of Lading -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                📋
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Conocimientos</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                {{ $stats['bills_count'] ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contenedores -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                🏗️
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Contenedores</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                {{ $stats['containers_count'] ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalles del viaje creado -->
            @if($voyage)
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🚢 Viaje Creado</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Número de Viaje</dt>
                                    <dd class="text-sm text-gray-900 font-semibold">{{ $voyage['voyage_number'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Embarcación</dt>
                                    <dd class="text-sm text-gray-900">{{ $voyage['vessel_name'] ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                    <dd class="text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $voyage['status'] ?? 'Planificación' }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Puerto Origen</dt>
                                    <dd class="text-sm text-gray-900">{{ $voyage['origin_port'] ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Puerto Destino</dt>
                                    <dd class="text-sm text-gray-900">{{ $voyage['destination_port'] ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Fecha Salida</dt>
                                    <dd class="text-sm text-gray-900">{{ $voyage['departure_date'] ?? 'Por definir' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex space-x-3">
                        <a href="{{ route('company.voyages.show', $voyage['id']) }}" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                            👁️ Ver Viaje
                        </a>
                        <a href="{{ route('company.manifests.show', $voyage['id']) }}" 
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-indigo-700">
                            📋 Ver Manifiesto
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Lista de registros creados -->
            @if(!empty($createdRecords))
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📊 Registros Creados</h3>
                </div>
                <div class="px-6 py-4">
                    
                    <!-- Conocimientos de Embarque -->
                    @if(!empty($createdRecords['billsOfLading']))
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-800 mb-3">📋 Conocimientos de Embarque ({{ count($createdRecords['billsOfLading']) }})</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BL Number</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cargador</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consignatario</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($createdRecords['billsOfLading'] as $bl)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $bl['bl_number'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $bl['shipper_name'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $bl['consignee_name'] ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $bl['items_count'] ?? 0 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('company.bills-of-lading.show', $bl['id']) }}" 
                                               class="text-indigo-600 hover:text-indigo-900">Ver</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    <!-- Contenedores -->
                    @if(!empty($createdRecords['containers']))
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-800 mb-3">🏗️ Contenedores ({{ count($createdRecords['containers']) }})</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($createdRecords['containers'] as $container)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $container['container_number'] ?? 'N/A' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Tipo: {{ $container['container_type'] ?? 'N/A' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Peso: {{ $container['gross_weight'] ?? 0 }} kg
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                </div>
            </div>
            @endif

            <!-- Advertencias y errores -->
            @if(!empty($warnings) || !empty($errors))
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">⚠️ Advertencias y Errores</h3>
                </div>
                <div class="px-6 py-4">
                    
                    @if(!empty($warnings))
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-yellow-800 mb-3">⚠️ Advertencias ({{ count($warnings) }})</h4>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                            <ul class="text-sm text-yellow-700 space-y-1">
                                @foreach($warnings as $warning)
                                <li>• {{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif

                    @if(!empty($errors))
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-red-800 mb-3">❌ Errores ({{ count($errors) }})</h4>
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <ul class="text-sm text-red-700 space-y-1">
                                @foreach($errors as $error)
                                <li>• {{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif

                </div>
            </div>
            @endif

            <!-- Botones de acción -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">🚀 Próximos Pasos</h3>
                <div class="flex flex-wrap gap-3">
                    
                    <a href="{{ route('company.manifests.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                        📋 Ver Todos los Manifiestos
                    </a>

                    @if($voyage)
                    <a href="{{ route('company.manifests.show', $voyage['id']) }}" 
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-indigo-700">
                        👁️ Ver Este Manifiesto
                    </a>

                    <a href="{{ route('company.manifests.export.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-purple-700">
                        📤 Exportar Manifiesto
                    </a>

                    <a href="{{ route('company.manifests.customs.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700">
                        🏛️ Enviar a Aduana
                    </a>
                    @endif

                    <a href="{{ route('company.manifests.import.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-gray-700">
                        📊 Importar Otro Archivo
                    </a>

                    <a href="{{ route('company.manifests.import.history') }}" 
                       class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-yellow-700">
                        📈 Ver Historial
                    </a>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>