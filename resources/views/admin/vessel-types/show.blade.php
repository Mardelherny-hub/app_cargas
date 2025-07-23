<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detalle de Tipo de Embarcación') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Header con título y acciones -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <!-- Icono de categoría -->
                        <div class="flex-shrink-0">
                            <div class="h-12 w-12 rounded-full flex items-center justify-center" 
                                 style="background-color: {{ $vesselType->color_code ?? '#6B7280' }}">
                                <span class="text-white text-lg font-bold">
                                    {{ strtoupper(substr($vesselType->category, 0, 1)) }}
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">{{ $vesselType->name }}</h1>
                            <div class="flex items-center space-x-4 mt-1">
                                <span class="text-sm text-gray-600">Código: <strong>{{ $vesselType->code }}</strong></span>
                                @if($vesselType->short_name)
                                    <span class="text-sm text-gray-600">{{ $vesselType->short_name }}</span>
                                @endif
                                
                                <!-- Badges de estado -->
                                <div class="flex space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                               {{ $vesselType->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $vesselType->active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                    
                                    @if($vesselType->is_common)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Común
                                        </span>
                                    @endif
                                    
                                    @if($vesselType->is_specialized)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                            Especializado
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="flex space-x-2">
                        <a href="{{ route('admin.vessel-types.edit', $vesselType) }}" 
                           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Editar
                        </a>
                        
                        <button onclick="showDuplicateModal()" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            Duplicar
                        </button>
                        
                        <!-- Toggle Status -->
                        <form action="{{ route('admin.vessel-types.toggle-status', $vesselType) }}" method="POST" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" 
                                    class="bg-{{ $vesselType->active ? 'red' : 'green' }}-600 hover:bg-{{ $vesselType->active ? 'red' : 'green' }}-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    @if($vesselType->active)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    @endif
                                </svg>
                                {{ $vesselType->active ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                        
                        <a href="{{ route('admin.vessel-types.index') }}" 
                           class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Volver al Listado
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alertas de sesión -->
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Columna Principal (2/3) -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Información Básica -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Información Básica</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Categoría</label>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                                   @switch($vesselType->category)
                                                       @case('barge') bg-blue-100 text-blue-800 @break
                                                       @case('tugboat') bg-orange-100 text-orange-800 @break
                                                       @case('pusher') bg-red-100 text-red-800 @break
                                                       @case('self_propelled') bg-purple-100 text-purple-800 @break
                                                       @case('mixed') bg-green-100 text-green-800 @break
                                                       @default bg-gray-100 text-gray-800
                                                   @endswitch">
                                            {{ $vesselType->category_name }}
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Tipo de Propulsión</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $vesselType->propulsion_type_name }}</p>
                                </div>

                                @if($vesselType->description)
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-500">Descripción</label>
                                        <p class="mt-1 text-sm text-gray-900">{{ $vesselType->description }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Especificaciones Físicas -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Especificaciones Físicas</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <!-- Longitud -->
                                @if($vesselType->min_length || $vesselType->max_length)
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600">
                                            @if($vesselType->min_length && $vesselType->max_length)
                                                {{ number_format($vesselType->min_length, 1) }}-{{ number_format($vesselType->max_length, 1) }}
                                            @elseif($vesselType->max_length)
                                                ≤{{ number_format($vesselType->max_length, 1) }}
                                            @else
                                                ≥{{ number_format($vesselType->min_length, 1) }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">Longitud (m)</div>
                                    </div>
                                @endif

                                <!-- Manga -->
                                @if($vesselType->min_beam || $vesselType->max_beam)
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600">
                                            @if($vesselType->min_beam && $vesselType->max_beam)
                                                {{ number_format($vesselType->min_beam, 1) }}-{{ number_format($vesselType->max_beam, 1) }}
                                            @elseif($vesselType->max_beam)
                                                ≤{{ number_format($vesselType->max_beam, 1) }}
                                            @else
                                                ≥{{ number_format($vesselType->min_beam, 1) }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">Manga (m)</div>
                                    </div>
                                @endif

                                <!-- Calado -->
                                @if($vesselType->min_draft || $vesselType->max_draft)
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-yellow-600">
                                            @if($vesselType->min_draft && $vesselType->max_draft)
                                                {{ number_format($vesselType->min_draft, 1) }}-{{ number_format($vesselType->max_draft, 1) }}
                                            @elseif($vesselType->max_draft)
                                                ≤{{ number_format($vesselType->max_draft, 1) }}
                                            @else
                                                ≥{{ number_format($vesselType->min_draft, 1) }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">Calado (m)</div>
                                    </div>
                                @endif

                                <!-- Profundidad mínima -->
                                @if($vesselType->min_water_depth)
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-cyan-600">
                                            {{ number_format($vesselType->min_water_depth, 1) }}
                                        </div>
                                        <div class="text-sm text-gray-500">Prof. Mín. (m)</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Capacidades de Carga -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Capacidades de Carga</h3>
                        </div>
                        <div class="px-6 py-4">
                            <!-- Capacidades Numéricas -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                @if($vesselType->min_cargo_capacity || $vesselType->max_cargo_capacity)
                                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                                        <div class="text-xl font-bold text-gray-900">
                                            @if($vesselType->min_cargo_capacity && $vesselType->max_cargo_capacity)
                                                {{ number_format($vesselType->min_cargo_capacity, 0) }}-{{ number_format($vesselType->max_cargo_capacity, 0) }}
                                            @elseif($vesselType->max_cargo_capacity)
                                                ≤{{ number_format($vesselType->max_cargo_capacity, 0) }}
                                            @else
                                                ≥{{ number_format($vesselType->min_cargo_capacity, 0) }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">Carga (ton)</div>
                                    </div>
                                @endif

                                @if($vesselType->min_container_capacity || $vesselType->max_container_capacity)
                                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                                        <div class="text-xl font-bold text-blue-900">
                                            @if($vesselType->min_container_capacity && $vesselType->max_container_capacity)
                                                {{ number_format($vesselType->min_container_capacity, 0) }}-{{ number_format($vesselType->max_container_capacity, 0) }}
                                            @elseif($vesselType->max_container_capacity)
                                                ≤{{ number_format($vesselType->max_container_capacity, 0) }}
                                            @else
                                                ≥{{ number_format($vesselType->min_container_capacity, 0) }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-blue-600">Contenedores</div>
                                    </div>
                                @endif

                                @if($vesselType->min_liquid_capacity || $vesselType->max_liquid_capacity)
                                    <div class="text-center p-4 bg-yellow-50 rounded-lg">
                                        <div class="text-xl font-bold text-yellow-900">
                                            @if($vesselType->min_liquid_capacity && $vesselType->max_liquid_capacity)
                                                {{ number_format($vesselType->min_liquid_capacity, 0) }}-{{ number_format($vesselType->max_liquid_capacity, 0) }}
                                            @elseif($vesselType->max_liquid_capacity)
                                                ≤{{ number_format($vesselType->max_liquid_capacity, 0) }}
                                            @else
                                                ≥{{ number_format($vesselType->min_liquid_capacity, 0) }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-yellow-600">Líquidos (m³)</div>
                                    </div>
                                @endif
                            </div>

                            <!-- Tipos de Carga -->
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-3">Tipos de Carga Soportados</label>
                                <div class="flex flex-wrap gap-2">
                                    @if($vesselType->handles_containers)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Contenedores
                                        </span>
                                    @endif
                                    @if($vesselType->handles_bulk_cargo)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Carga a Granel
                                        </span>
                                    @endif
                                    @if($vesselType->handles_general_cargo)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Carga General
                                        </span>
                                    @endif
                                    @if($vesselType->handles_liquid_cargo)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Carga Líquida
                                        </span>
                                    @endif
                                    @if($vesselType->handles_dangerous_goods)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Mercancías Peligrosas
                                        </span>
                                    @endif
                                    @if($vesselType->handles_refrigerated_cargo)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800">
                                            Carga Refrigerada
                                        </span>
                                    @endif
                                    @if($vesselType->handles_oversized_cargo)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            Carga Sobredimensionada
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navegación y Convoy -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Navegación y Convoy</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Tipos de Navegación -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-3">Tipos de Navegación</label>
                                    <div class="space-y-2">
                                        @if($vesselType->river_navigation)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Navegación Fluvial</span>
                                            </div>
                                        @endif
                                        @if($vesselType->maritime_navigation)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Navegación Marítima</span>
                                            </div>
                                        @endif
                                        @if($vesselType->coastal_navigation)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Navegación Costera</span>
                                            </div>
                                        @endif
                                        @if($vesselType->lake_navigation)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Navegación Lacustre</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Capacidades de Convoy -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-3">Capacidades de Convoy</label>
                                    <div class="space-y-2">
                                        @if($vesselType->can_be_lead_vessel)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Puede ser Embarcación Líder</span>
                                            </div>
                                        @endif
                                        @if($vesselType->can_be_in_convoy)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Puede ir en Convoy</span>
                                            </div>
                                        @endif
                                        @if($vesselType->can_push_barges)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Puede Empujar Barcazas</span>
                                            </div>
                                        @endif
                                        @if($vesselType->can_tow_barges)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Puede Remolcar Barcazas</span>
                                            </div>
                                        @endif
                                        @if($vesselType->max_barges_in_convoy)
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span class="text-sm text-gray-900">Máximo {{ $vesselType->max_barges_in_convoy }} barcazas en convoy</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Características Operativas -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Características Operativas</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                                @if($vesselType->typical_crew_size || $vesselType->max_crew_size)
                                    <div class="text-center">
                                        <div class="text-lg font-semibold text-gray-900">
                                            @if($vesselType->typical_crew_size && $vesselType->max_crew_size)
                                                {{ $vesselType->typical_crew_size }}-{{ $vesselType->max_crew_size }}
                                            @elseif($vesselType->max_crew_size)
                                                ≤{{ $vesselType->max_crew_size }}
                                            @else
                                                ≥{{ $vesselType->typical_crew_size }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">Tripulación</div>
                                    </div>
                                @endif

                                @if($vesselType->typical_speed || $vesselType->max_speed)
                                    <div class="text-center">
                                        <div class="text-lg font-semibold text-gray-900">
                                            @if($vesselType->typical_speed && $vesselType->max_speed)
                                                {{ $vesselType->typical_speed }}-{{ $vesselType->max_speed }}
                                            @elseif($vesselType->max_speed)
                                                ≤{{ $vesselType->max_speed }}
                                            @else
                                                ≥{{ $vesselType->typical_speed }}
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">Velocidad (nudos)</div>
                                    </div>
                                @endif

                                @if($vesselType->fuel_consumption_per_day)
                                    <div class="text-center">
                                        <div class="text-lg font-semibold text-gray-900">
                                            {{ number_format($vesselType->fuel_consumption_per_day) }}
                                        </div>
                                        <div class="text-sm text-gray-500">Consumo (L/día)</div>
                                    </div>
                                @endif

                                @if($vesselType->typical_voyage_duration)
                                    <div class="text-center">
                                        <div class="text-lg font-semibold text-gray-900">
                                            {{ $vesselType->typical_voyage_duration }}
                                        </div>
                                        <div class="text-sm text-gray-500">Duración (días)</div>
                                    </div>
                                @endif
                            </div>

                            @if($vesselType->loading_time_hours || $vesselType->unloading_time_hours)
                                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                    @if($vesselType->loading_time_hours)
                                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                                            <div class="text-lg font-semibold text-blue-900">
                                                {{ $vesselType->loading_time_hours }}h
                                            </div>
                                            <div class="text-sm text-blue-600">Tiempo de Carga</div>
                                        </div>
                                    @endif

                                    @if($vesselType->unloading_time_hours)
                                        <div class="text-center p-3 bg-green-50 rounded-lg">
                                            <div class="text-lg font-semibold text-green-900">
                                                {{ $vesselType->unloading_time_hours }}h
                                            </div>
                                            <div class="text-sm text-green-600">Tiempo de Descarga</div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Columna Lateral (1/3) -->
                <div class="lg:col-span-1 space-y-6">

                    <!-- Resumen Rápido -->  
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Resumen</h3>
                        </div>
                        <div class="px-6 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Orden de Visualización</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $vesselType->display_order }}</p>
                            </div>

                            @if($vesselType->vessels_count !== null)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Embarcaciones Registradas</label>
                                    <p class="mt-1 text-2xl font-bold text-blue-600">{{ $vesselType->vessels_count }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Requisitos y Certificaciones -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Requisitos</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="space-y-3">
                                @if($vesselType->requires_pilot)
                                    <div class="flex items-center text-sm">
                                        <svg class="w-4 h-4 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-gray-900">Requiere Piloto</span>
                                    </div>
                                @endif
                                
                                @if($vesselType->requires_tugboat_assistance)
                                    <div class="flex items-center text-sm">
                                        <svg class="w-4 h-4 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-gray-900">Requiere Asistencia Remolcador</span>
                                    </div>
                                @endif

                                @if($vesselType->requires_special_permits)
                                    <div class="flex items-center text-sm">
                                        <svg class="w-4 h-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-gray-900">Requiere Permisos Especiales</span>
                                    </div>
                                @endif

                                @if($vesselType->requires_insurance)
                                    <div class="flex items-center text-sm">
                                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-gray-900">Requiere Seguro</span>
                                    </div>
                                @endif

                                @if($vesselType->requires_safety_certificate)
                                    <div class="flex items-center text-sm">
                                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-gray-900">Requiere Certificado Seguridad</span>
                                    </div>
                                @endif

                                @if($vesselType->requires_dry_dock)
                                    <div class="flex items-center text-sm">
                                        <svg class="w-4 h-4 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-gray-900">Requiere Dique Seco</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Códigos e Identificación -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Códigos e Identificación</h3>
                        </div>
                        <div class="px-6 py-4 space-y-4">
                            @if($vesselType->imo_type_code)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Código IMO</label>
                                    <p class="mt-1 text-sm font-mono text-gray-900">{{ $vesselType->imo_type_code }}</p>
                                </div>
                            @endif

                            @if($vesselType->inland_vessel_code)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Código Embarcación Fluvial</label>
                                    <p class="mt-1 text-sm font-mono text-gray-900">{{ $vesselType->inland_vessel_code }}</p>
                                </div>
                            @endif

                            @if($vesselType->imdg_class)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Clase IMDG</label>
                                    <p class="mt-1 text-sm font-mono text-gray-900">{{ $vesselType->imdg_class }}</p>
                                </div>
                            @endif

                            @if($vesselType->argentina_ws_code)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Código WS Argentina</label>
                                    <p class="mt-1 text-sm font-mono text-gray-900">{{ $vesselType->argentina_ws_code }}</p>
                                </div>
                            @endif

                            @if($vesselType->paraguay_ws_code)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Código WS Paraguay</label>
                                    <p class="mt-1 text-sm font-mono text-gray-900">{{ $vesselType->paraguay_ws_code }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Información Económica -->
                    @if($vesselType->daily_charter_rate || $vesselType->fuel_cost_per_day)
                        <div class="bg-white shadow rounded-lg">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Información Económica</h3>
                            </div>
                            <div class="px-6 py-4 space-y-4">
                                @if($vesselType->daily_charter_rate)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500">Tarifa Diaria</label>
                                        <p class="mt-1 text-lg font-semibold text-green-600">
                                            ${{ number_format($vesselType->daily_charter_rate, 2) }} USD
                                        </p>
                                    </div>
                                @endif

                                @if($vesselType->fuel_cost_per_day)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500">Costo Combustible/Día</label>
                                        <p class="mt-1 text-lg font-semibold text-red-600">
                                            ${{ number_format($vesselType->fuel_cost_per_day, 2) }} USD
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Mantenimiento -->
                    @if($vesselType->typical_lifespan_years || $vesselType->maintenance_interval_days || $vesselType->dry_dock_interval_months)
                        <div class="bg-white shadow rounded-lg">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900">Mantenimiento</h3>
                            </div>
                            <div class="px-6 py-4 space-y-4">
                                @if($vesselType->typical_lifespan_years)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500">Vida Útil</label>
                                        <p class="mt-1 text-sm text-gray-900">{{ $vesselType->typical_lifespan_years }} años</p>
                                    </div>
                                @endif

                                @if($vesselType->maintenance_interval_days)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500">Intervalo Mantenimiento</label>
                                        <p class="mt-1 text-sm text-gray-900">{{ $vesselType->maintenance_interval_text }}</p>
                                    </div>
                                @endif

                                @if($vesselType->dry_dock_interval_months)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500">Intervalo Dique Seco</label>
                                        <p class="mt-1 text-sm text-gray-900">{{ $vesselType->dry_dock_interval_months }} meses</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Información de Auditoría -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Información de Registro</h3>
                        </div>
                        <div class="px-6 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Creado</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $vesselType->created_at->format('d/m/Y H:i') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Última Actualización</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $vesselType->updated_at->format('d/m/Y H:i') }}</p>
                            </div>

                            @if($vesselType->createdByUser)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Creado por</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $vesselType->createdByUser->name }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Duplicar -->
            <div id="duplicate-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Duplicar Tipo de Embarcación</h3>
                        <form action="{{ route('admin.vessel-types.duplicate', $vesselType) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label for="new_code" class="block text-sm font-medium text-gray-700">Nuevo Código *</label>
                                <input type="text" name="new_code" id="new_code" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label for="new_name" class="block text-sm font-medium text-gray-700">Nuevo Nombre *</label>
                                <input type="text" name="new_name" id="new_name" required
                                       value="Copia de {{ $vesselType->name }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="copy_specifications" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm text-gray-700">Copiar especificaciones técnicas</span>
                                </label>
                            </div>
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="copy_capabilities" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm text-gray-700">Copiar capacidades de carga</span>
                                </label>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button type="button" onclick="hideDuplicateModal()" 
                                        class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </button>
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                    Duplicar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showDuplicateModal() {
        document.getElementById('duplicate-modal').classList.remove('hidden');
    }

    function hideDuplicateModal() {
        document.getElementById('duplicate-modal').classList.add('hidden');
    }
    </script>
</x-app-layout>