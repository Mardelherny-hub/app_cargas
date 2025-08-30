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
                        <div class="text-3xl font-bold">
                            {{ count($createdRecords['billsOfLading'] ?? []) }}
                        </div>
                        <div class="text-green-100 text-sm uppercase tracking-wide">
                            Conocimientos
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🚀 NUEVA SECCIÓN: Accesos Directos Post-Importación -->
            @if($importResult['success'] || $importResult['hasWarnings'])
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
                <div class="flex items-center mb-4">
                    <div class="bg-blue-100 p-2 rounded-lg mr-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-900">Accesos Rápidos</h4>
                    <span class="ml-2 text-sm text-gray-500">Accede directamente a la información importada</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    
                    <!-- Acceso directo al Viaje -->
                    @if(!empty($voyage))
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h5 class="font-medium text-gray-900">📋 Viaje Creado</h5>
                                <p class="text-sm text-gray-600">{{ $voyage['voyage_number'] }}</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ ucfirst($voyage['status']) }}
                                </span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mb-3">
                            {{ $voyage['origin_port'] }} → {{ $voyage['destination_port'] }}
                            @if($voyage['departure_date'])
                                <br>Salida: {{ $voyage['departure_date'] }}
                            @endif
                        </div>
                        <a href="{{ route('company.voyages.show', $voyage['id']) }}" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-purple-700 bg-purple-100 hover:bg-purple-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Ver Viaje
                        </a>
                    </div>
                    @endif

                    <!-- Acceso directo a Conocimientos -->
                    @if(!empty($createdRecords['billsOfLading']))
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h5 class="font-medium text-gray-900">📄 Conocimientos</h5>
                                <p class="text-sm text-gray-600">{{ count($createdRecords['billsOfLading']) }} importados</p>
                            </div>
                            <div class="bg-blue-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold">
                                {{ count($createdRecords['billsOfLading']) }}
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mb-3">
                            Consultar por conocimiento usando el buscador global o acceder al listado filtrado
                        </div>
                        @if(!empty($voyage))
                        <a href="{{ route('company.bills-of-lading.index', ['voyage_id' => $voyage['id']]) }}" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Ver Conocimientos
                        </a>
                        @endif
                    </div>
                    @endif

                    <!-- Acceso directo a Contenedores -->
                    @if(!empty($createdRecords['containers']))
                    <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-4 border border-orange-200">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h5 class="font-medium text-gray-900">📦 Contenedores</h5>
                                <p class="text-sm text-gray-600">{{ count($createdRecords['containers']) }} importados</p>
                            </div>
                            <div class="bg-orange-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold">
                                {{ count($createdRecords['containers']) }}
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mb-3">
                            Lista de contenedores asociados al viaje importado
                        </div>
                        @if(!empty($voyage))
                        <a href="{{-- r#oute('company.containers.index', ['voyage_id' => $voyage['id']]) --}}#" 
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-orange-700 bg-orange-100 hover:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-all duration-200">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            Ver Contenedores
                        </a>
                        @endif
                    </div>
                    @endif
                </div>

                <!-- Primeros Conocimientos para Acceso Directo -->
                @if(!empty($createdRecords['billsOfLading']) && count($createdRecords['billsOfLading']) > 0)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h5 class="font-medium text-gray-900 mb-3">🔍 Acceso Directo a Conocimientos</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach(array_slice($createdRecords['billsOfLading'], 0, 6) as $bl)
                        <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $bl['bl_number'] }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $bl['shipper_name'] }}</p>
                                    <p class="text-xs text-gray-400">{{ $bl['items_count'] }} items</p>
                                </div>
                                <a href="{{ route('company.bills-of-lading.show', $bl['id']) }}" 
                                   class="ml-2 inline-flex items-center p-1 border border-transparent rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @if(count($createdRecords['billsOfLading']) > 6)
                    <div class="mt-3 text-center">
                        <span class="text-sm text-gray-500">Y {{ count($createdRecords['billsOfLading']) - 6 }} conocimientos más...</span>
                    </div>
                    @endif
                </div>
                @endif
            </div>
            @endif

            <!-- Estadísticas detalladas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ count($createdRecords['billsOfLading'] ?? []) }}</p>
                            <p class="text-gray-600 text-sm">Conocimientos de Embarque</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-orange-100 rounded-lg mr-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ count($createdRecords['containers'] ?? []) }}</p>
                            <p class="text-gray-600 text-sm">Contenedores</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $stats['processed_items'] ?? count($createdRecords['billsOfLading'] ?? []) }}</p>
                            <p class="text-gray-600 text-sm">Items Procesados</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalles del viaje (si existe) -->
            @if(!empty($voyage))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
                <h4 class="text-lg font-semibold text-gray-900 mb-4">📋 Información del Viaje</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Número de Viaje:</dt>
                                <dd class="text-sm text-gray-900">{{ $voyage['voyage_number'] }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Estado:</dt>
                                <dd class="text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ ucfirst($voyage['status']) }}
                                    </span>
                                </dd>
                            </div>
                            @if($voyage['vessel_name'])
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Embarcación:</dt>
                                <dd class="text-sm text-gray-900">{{ $voyage['vessel_name'] }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                    <div>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Origen:</dt>
                                <dd class="text-sm text-gray-900">{{ $voyage['origin_port'] ?? 'N/A' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Destino:</dt>
                                <dd class="text-sm text-gray-900">{{ $voyage['destination_port'] ?? 'N/A' }}</dd>
                            </div>
                            @if($voyage['departure_date'])
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Fecha de Salida:</dt>
                                <dd class="text-sm text-gray-900">{{ $voyage['departure_date'] }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
            @endif

            <!-- Advertencias (si las hay) -->
            @if(!empty($warnings))
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Advertencias de Importación</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($warnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Errores (si los hay) -->
            @if(!empty($errors))
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Errores de Importación</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($errors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Botones de acción -->
            <div class="flex flex-wrap gap-4 justify-center">
                <a href="{{ route('company.manifests.import.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Nueva Importación
                </a>
                
                <a href="{{ route('company.manifests.import.history') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Ver Historial
                </a>

                @if(!empty($voyage))
                <a href="{{ route('company.voyages.show', $voyage['id']) }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Ir al Viaje
                </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>