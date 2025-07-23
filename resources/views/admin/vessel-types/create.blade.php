<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Crear Tipo de Embarcación') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Header con navegación -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Crear Nuevo Tipo de Embarcación</h1>
                        <p class="mt-1 text-sm text-gray-600">Complete la información del nuevo tipo de embarcación</p>
                    </div>
                    <div class="flex space-x-2">
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

            <!-- Alertas de validación -->
            @if($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">¡Errores de validación!</strong>
                    <ul class="mt-2 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Formulario -->
            <form method="POST" action="{{ route('admin.vessel-types.store') }}" class="space-y-8" id="vessel-type-form">
                @csrf

                <!-- Información Básica -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Información Básica</h3>
                        <p class="mt-1 text-sm text-gray-500">Datos principales del tipo de embarcación</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- Código -->
                            <div>
                                <label for="code" class="block text-sm font-medium text-gray-700">
                                    Código *
                                </label>
                                <input type="text" name="code" id="code" value="{{ old('code') }}" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Ej: BARGE_STD_001">
                                <p class="mt-1 text-xs text-gray-500">Código único identificador del tipo</p>
                                @error('code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Nombre -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">
                                    Nombre *
                                </label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Ej: Barcaza Estándar de Contenedores">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Nombre Corto -->
                            <div>
                                <label for="short_name" class="block text-sm font-medium text-gray-700">
                                    Nombre Corto
                                </label>
                                <input type="text" name="short_name" id="short_name" value="{{ old('short_name') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Ej: Barcaza Contenedores">
                                <p class="mt-1 text-xs text-gray-500">Para mostrar en interfaces compactas</p>
                                @error('short_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Categoría -->
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">
                                    Categoría *
                                </label>
                                <select name="category" id="category" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Seleccionar categoría</option>
                                    <option value="barge" {{ old('category') == 'barge' ? 'selected' : '' }}>Barcaza</option>
                                    <option value="tugboat" {{ old('category') == 'tugboat' ? 'selected' : '' }}>Remolcador</option>
                                    <option value="pusher" {{ old('category') == 'pusher' ? 'selected' : '' }}>Empujador</option>
                                    <option value="self_propelled" {{ old('category') == 'self_propelled' ? 'selected' : '' }}>Autopropulsado</option>
                                    <option value="mixed" {{ old('category') == 'mixed' ? 'selected' : '' }}>Mixto</option>
                                </select>
                                @error('category')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Propulsión -->
                            <div>
                                <label for="propulsion_type" class="block text-sm font-medium text-gray-700">
                                    Tipo de Propulsión *
                                </label>
                                <select name="propulsion_type" id="propulsion_type" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Seleccionar propulsión</option>
                                    <option value="self_propelled" {{ old('propulsion_type') == 'self_propelled' ? 'selected' : '' }}>Autopropulsado</option>
                                    <option value="towed" {{ old('propulsion_type') == 'towed' ? 'selected' : '' }}>Remolcado</option>  
                                    <option value="pushed" {{ old('propulsion_type') == 'pushed' ? 'selected' : '' }}>Empujado</option>
                                    <option value="hybrid" {{ old('propulsion_type') == 'hybrid' ? 'selected' : '' }}>Híbrido</option>
                                </select>
                                @error('propulsion_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Orden de visualización -->
                            <div>
                                <label for="display_order" class="block text-sm font-medium text-gray-700">
                                    Orden de Visualización
                                </label>
                                <input type="number" name="display_order" id="display_order" value="{{ old('display_order', 999) }}" min="1"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Orden en listados (menor número = primera posición)</p>
                                @error('display_order')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="mt-6">
                            <label for="description" class="block text-sm font-medium text-gray-700">
                                Descripción
                            </label>
                            <textarea name="description" id="description" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Descripción detallada del tipo de embarcación...">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Especificaciones Físicas -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Especificaciones Físicas</h3>
                        <p class="mt-1 text-sm text-gray-500">Dimensiones y características físicas</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- Longitud -->
                            <div class="lg:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Longitud (metros)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input type="number" name="min_length" id="min_length" value="{{ old('min_length') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Mínima">
                                    </div>
                                    <div>
                                        <input type="number" name="max_length" id="max_length" value="{{ old('max_length') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Máxima">
                                    </div>
                                </div>
                                @error('min_length')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                @error('max_length')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <!-- Manga -->
                            <div class="lg:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Manga (metros)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input type="number" name="min_beam" id="min_beam" value="{{ old('min_beam') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Mínima">
                                    </div>
                                    <div>
                                        <input type="number" name="max_beam" id="max_beam" value="{{ old('max_beam') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Máxima">
                                    </div>
                                </div>
                                @error('min_beam')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                @error('max_beam')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <!-- Calado -->
                            <div class="lg:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Calado (metros)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input type="number" name="min_draft" id="min_draft" value="{{ old('min_draft') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Mínimo">
                                    </div>
                                    <div>
                                        <input type="number" name="max_draft" id="max_draft" value="{{ old('max_draft') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Máximo">
                                    </div>
                                </div>
                                @error('min_draft')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                @error('max_draft')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <!-- Profundidad mínima de agua -->
                            <div>
                                <label for="min_water_depth" class="block text-sm font-medium text-gray-700">
                                    Profundidad Mínima Requerida (metros)
                                </label>
                                <input type="number" name="min_water_depth" id="min_water_depth" value="{{ old('min_water_depth') }}" 
                                       step="0.01" min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('min_water_depth')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Capacidades de Carga -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Capacidades de Carga</h3>
                        <p class="mt-1 text-sm text-gray-500">Capacidades y tipos de carga soportados</p>
                    </div>
                    <div class="px-6 py-4">
                        <!-- Capacidades Numéricas -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <!-- Capacidad General -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Capacidad de Carga (toneladas)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input type="number" name="min_cargo_capacity" id="min_cargo_capacity" value="{{ old('min_cargo_capacity') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Mínima">
                                    </div>
                                    <div>
                                        <input type="number" name="max_cargo_capacity" id="max_cargo_capacity" value="{{ old('max_cargo_capacity') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Máxima">
                                    </div>
                                </div>
                                @error('min_cargo_capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                @error('max_cargo_capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <!-- Capacidad Contenedores -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Capacidad Contenedores (unidades)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input type="number" name="min_container_capacity" id="min_container_capacity" value="{{ old('min_container_capacity') }}" 
                                               min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Mínima">
                                    </div>
                                    <div>
                                        <input type="number" name="max_container_capacity" id="max_container_capacity" value="{{ old('max_container_capacity') }}" 
                                               min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Máxima">
                                    </div>
                                </div>
                                @error('min_container_capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                @error('max_container_capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <!-- Capacidad Líquidos -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Capacidad Líquidos (m³)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input type="number" name="min_liquid_capacity" id="min_liquid_capacity" value="{{ old('min_liquid_capacity') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Mínima">
                                    </div>
                                    <div>
                                        <input type="number" name="max_liquid_capacity" id="max_liquid_capacity" value="{{ old('max_liquid_capacity') }}" 
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Máxima">
                                    </div>
                                </div>
                                @error('min_liquid_capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                @error('max_liquid_capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <!-- Tipos de Carga -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Tipos de Carga Soportados</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                <!-- Contenedores -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="handles_containers" id="handles_containers" value="1" 
                                           {{ old('handles_containers') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <label for="handles_containers" class="ml-2 text-sm text-gray-700">Contenedores</label>
                                </div>

                                <!-- Granel -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="handles_bulk_cargo" id="handles_bulk_cargo" value="1" 
                                           {{ old('handles_bulk_cargo') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <label for="handles_bulk_cargo" class="ml-2 text-sm text-gray-700">Carga a Granel</label>
                                </div>

                                <!-- General -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="handles_general_cargo" id="handles_general_cargo" value="1" 
                                           {{ old('handles_general_cargo') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <label for="handles_general_cargo" class="ml-2 text-sm text-gray-700">Carga General</label>
                                </div>

                                <!-- Líquida -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="handles_liquid_cargo" id="handles_liquid_cargo" value="1" 
                                           {{ old('handles_liquid_cargo') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <label for="handles_liquid_cargo" class="ml-2 text-sm text-gray-700">Carga Líquida</label>
                                </div>

                                <!-- Peligrosa -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="handles_dangerous_goods" id="handles_dangerous_goods" value="1" 
                                           {{ old('handles_dangerous_goods') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <label for="handles_dangerous_goods" class="ml-2 text-sm text-gray-700">Mercancías Peligrosas</label>
                                </div>

                                <!-- Refrigerada -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="handles_refrigerated_cargo" id="handles_refrigerated_cargo" value="1" 
                                           {{ old('handles_refrigerated_cargo') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <label for="handles_refrigerated_cargo" class="ml-2 text-sm text-gray-700">Carga Refrigerada</label>
                                </div>

                                <!-- Sobredimensionada -->
                                <div class="flex items-center">
                                    <input type="checkbox" name="handles_oversized_cargo" id="handles_oversized_cargo" value="1" 
                                           {{ old('handles_oversized_cargo') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                    <label for="handles_oversized_cargo" class="ml-2 text-sm text-gray-700">Carga Sobredimensionada</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navegación y Operación -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Navegación y Operación</h3>
                        <p class="mt-1 text-sm text-gray-500">Capacidades de navegación y operación</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Tipos de Navegación -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Tipos de Navegación</label>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="river_navigation" id="river_navigation" value="1" 
                                               {{ old('river_navigation') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="river_navigation" class="ml-2 text-sm text-gray-700">Navegación Fluvial</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="maritime_navigation" id="maritime_navigation" value="1" 
                                               {{ old('maritime_navigation') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="maritime_navigation" class="ml-2 text-sm text-gray-700">Navegación Marítima</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="coastal_navigation" id="coastal_navigation" value="1" 
                                               {{ old('coastal_navigation') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="coastal_navigation" class="ml-2 text-sm text-gray-700">Navegación Costera</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="lake_navigation" id="lake_navigation" value="1" 
                                               {{ old('lake_navigation') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="lake_navigation" class="ml-2 text-sm text-gray-700">Navegación Lacustre</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Capacidades de Convoy -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Capacidades de Convoy</label>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="can_be_lead_vessel" id="can_be_lead_vessel" value="1" 
                                               {{ old('can_be_lead_vessel') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="can_be_lead_vessel" class="ml-2 text-sm text-gray-700">Puede ser Embarcación Líder</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="can_be_in_convoy" id="can_be_in_convoy" value="1" 
                                               {{ old('can_be_in_convoy') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="can_be_in_convoy" class="ml-2 text-sm text-gray-700">Puede ir en Convoy</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="can_push_barges" id="can_push_barges" value="1" 
                                               {{ old('can_push_barges') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="can_push_barges" class="ml-2 text-sm text-gray-700">Puede Empujar Barcazas</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="can_tow_barges" id="can_tow_barges" value="1" 
                                               {{ old('can_tow_barges') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="can_tow_barges" class="ml-2 text-sm text-gray-700">Puede Remolcar Barcazas</label>
                                    </div>
                                </div>

                                <!-- Máximo de barcazas en convoy -->
                                <div class="mt-4">
                                    <label for="max_barges_in_convoy" class="block text-sm font-medium text-gray-700">
                                        Máximo de Barcazas en Convoy
                                    </label>
                                    <input type="number" name="max_barges_in_convoy" id="max_barges_in_convoy" value="{{ old('max_barges_in_convoy') }}" 
                                           min="0"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="Ej: 8">
                                    @error('max_barges_in_convoy')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Características Operativas -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Características Operativas</h3>
                        <p class="mt-1 text-sm text-gray-500">Tripulación, velocidad y consumo</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Tripulación -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tripulación</label>
                                <div class="space-y-2">
                                    <input type="number" name="typical_crew_size" id="typical_crew_size" value="{{ old('typical_crew_size') }}" 
                                           min="0"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="Tripulación típica">
                                    <input type="number" name="max_crew_size" id="max_crew_size" value="{{ old('max_crew_size') }}" 
                                           min="0"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="Tripulación máxima">
                                </div>
                                @error('typical_crew_size')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                @error('max_crew_size')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <!-- Velocidad -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Velocidad (nudos)</label>
                                <div class="space-y-2">
                                    <input type="number" name="typical_speed" id="typical_speed" value="{{ old('typical_speed') }}" 
                                           step="0.1" min="0"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="Velocidad típica">
                                    <input type="number" name="max_speed" id="max_speed" value="{{ old('max_speed') }}" 
                                           step="0.1" min="0"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="Velocidad máxima">
                                </div>
                                @error('typical_speed')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                @error('max_speed')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <!-- Consumo -->
                            <div>
                                <label for="fuel_consumption_per_day" class="block text-sm font-medium text-gray-700">
                                    Consumo Combustible (litros/día)
                                </label>
                                <input type="number" name="fuel_consumption_per_day" id="fuel_consumption_per_day" value="{{ old('fuel_consumption_per_day') }}" 
                                       min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('fuel_consumption_per_day')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Duración típica de viaje -->
                            <div>
                                <label for="typical_voyage_duration" class="block text-sm font-medium text-gray-700">
                                    Duración Típica Viaje (días)
                                </label>
                                <input type="number" name="typical_voyage_duration" id="typical_voyage_duration" value="{{ old('typical_voyage_duration') }}" 
                                       min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('typical_voyage_duration')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Tiempos de carga/descarga -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="loading_time_hours" class="block text-sm font-medium text-gray-700">
                                    Tiempo de Carga (horas)
                                </label>
                                <input type="number" name="loading_time_hours" id="loading_time_hours" value="{{ old('loading_time_hours') }}" 
                                       min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('loading_time_hours')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="unloading_time_hours" class="block text-sm font-medium text-gray-700">
                                    Tiempo de Descarga (horas)
                                </label>
                                <input type="number" name="unloading_time_hours" id="unloading_time_hours" value="{{ old('unloading_time_hours') }}" 
                                       min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('unloading_time_hours')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requisitos y Certificaciones -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Requisitos y Certificaciones</h3>
                        <p class="mt-1 text-sm text-gray-500">Requisitos operacionales y certificaciones</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Requisitos Operacionales -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Requisitos Operacionales</label>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="requires_pilot" id="requires_pilot" value="1" 
                                               {{ old('requires_pilot') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="requires_pilot" class="ml-2 text-sm text-gray-700">Requiere Piloto</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="requires_tugboat_assistance" id="requires_tugboat_assistance" value="1" 
                                               {{ old('requires_tugboat_assistance') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="requires_tugboat_assistance" class="ml-2 text-sm text-gray-700">Requiere Asistencia de Remolcador</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="requires_special_permits" id="requires_special_permits" value="1" 
                                               {{ old('requires_special_permits') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="requires_special_permits" class="ml-2 text-sm text-gray-700">Requiere Permisos Especiales</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Certificaciones -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Certificaciones</label>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="requires_insurance" id="requires_insurance" value="1" 
                                               {{ old('requires_insurance', true) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="requires_insurance" class="ml-2 text-sm text-gray-700">Requiere Seguro</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="requires_safety_certificate" id="requires_safety_certificate" value="1" 
                                               {{ old('requires_safety_certificate', true) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="requires_safety_certificate" class="ml-2 text-sm text-gray-700">Requiere Certificado de Seguridad</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="requires_dry_dock" id="requires_dry_dock" value="1" 
                                               {{ old('requires_dry_dock') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="requires_dry_dock" class="ml-2 text-sm text-gray-700">Requiere Dique Seco</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Códigos de clasificación -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="imo_type_code" class="block text-sm font-medium text-gray-700">
                                    Código Tipo IMO
                                </label>
                                <input type="text" name="imo_type_code" id="imo_type_code" value="{{ old('imo_type_code') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('imo_type_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="inland_vessel_code" class="block text-sm font-medium text-gray-700">
                                    Código Embarcación Fluvial
                                </label>
                                <input type="text" name="inland_vessel_code" id="inland_vessel_code" value="{{ old('inland_vessel_code') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('inland_vessel_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="imdg_class" class="block text-sm font-medium text-gray-700">
                                    Clase IMDG
                                </label>
                                <input type="text" name="imdg_class" id="imdg_class" value="{{ old('imdg_class') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Para mercancías peligrosas">
                                @error('imdg_class')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Integración Webservices -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Integración Webservices</h3>
                        <p class="mt-1 text-sm text-gray-500">Códigos para integración con sistemas aduaneros</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="argentina_ws_code" class="block text-sm font-medium text-gray-700">
                                    Código Webservice Argentina
                                </label>
                                <input type="text" name="argentina_ws_code" id="argentina_ws_code" value="{{ old('argentina_ws_code') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Ej: BARCAZA_CONT">
                                @error('argentina_ws_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="paraguay_ws_code" class="block text-sm font-medium text-gray-700">
                                    Código Webservice Paraguay
                                </label>
                                <input type="text" name="paraguay_ws_code" id="paraguay_ws_code" value="{{ old('paraguay_ws_code') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Ej: BC_001">
                                @error('paraguay_ws_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Económica -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Información Económica</h3>
                        <p class="mt-1 text-sm text-gray-500">Costos y tarifas asociadas</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="daily_charter_rate" class="block text-sm font-medium text-gray-700">
                                    Tarifa Diaria de Alquiler (USD)
                                </label>
                                <input type="number" name="daily_charter_rate" id="daily_charter_rate" value="{{ old('daily_charter_rate') }}" 
                                       step="0.01" min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('daily_charter_rate')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="fuel_cost_per_day" class="block text-sm font-medium text-gray-700">
                                    Costo Combustible por Día (USD)
                                </label>
                                <input type="number" name="fuel_cost_per_day" id="fuel_cost_per_day" value="{{ old('fuel_cost_per_day') }}" 
                                       step="0.01" min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('fuel_cost_per_day')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mantenimiento -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Mantenimiento y Ciclo de Vida</h3>
                        <p class="mt-1 text-sm text-gray-500">Información sobre mantenimiento y vida útil</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="typical_lifespan_years" class="block text-sm font-medium text-gray-700">
                                    Vida Útil Típica (años)
                                </label>
                                <input type="number" name="typical_lifespan_years" id="typical_lifespan_years" value="{{ old('typical_lifespan_years') }}" 
                                       min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('typical_lifespan_years')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="maintenance_interval_days" class="block text-sm font-medium text-gray-700">
                                    Intervalo Mantenimiento (días)
                                </label>
                                <input type="number" name="maintenance_interval_days" id="maintenance_interval_days" value="{{ old('maintenance_interval_days') }}" 
                                       min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('maintenance_interval_days')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="dry_dock_interval_months" class="block text-sm font-medium text-gray-700">
                                    Intervalo Dique Seco (meses)
                                </label>
                                <input type="number" name="dry_dock_interval_months" id="dry_dock_interval_months" value="{{ old('dry_dock_interval_months') }}" 
                                       min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('dry_dock_interval_months')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuración y Estado -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Configuración y Estado</h3>
                        <p class="mt-1 text-sm text-gray-500">Estado y configuración visual</p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- Estado -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Estado del Tipo</label>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="active" id="active" value="1" 
                                               {{ old('active', true) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="active" class="ml-2 text-sm text-gray-700">Activo</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="is_common" id="is_common" value="1" 
                                               {{ old('is_common') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="is_common" class="ml-2 text-sm text-gray-700">Tipo Común</label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="is_specialized" id="is_specialized" value="1" 
                                               {{ old('is_specialized') ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                                        <label for="is_specialized" class="ml-2 text-sm text-gray-700">Tipo Especializado</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Configuración Visual -->
                            <div>
                                <div class="space-y-4">
                                    <div>
                                        <label for="icon" class="block text-sm font-medium text-gray-700">
                                            Icono
                                        </label>
                                        <input type="text" name="icon" id="icon" value="{{ old('icon') }}"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Nombre del icono">
                                        @error('icon')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="color_code" class="block text-sm font-medium text-gray-700">
                                            Color (Hex)
                                        </label>
                                        <input type="color" name="color_code" id="color_code" value="{{ old('color_code', '#3B82F6') }}"
                                               class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @error('color_code')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center justify-end space-x-4 pt-6">
                    <a href="{{ route('admin.vessel-types.index') }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-md text-sm font-medium">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Crear Tipo de Embarcación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-generar código basado en categoría y nombre
        const categorySelect = document.getElementById('category');
        const nameInput = document.getElementById('name');
        const codeInput = document.getElementById('code');

        function generateCode() {
            const category = categorySelect.value;
            const name = nameInput.value;
            
            if (category && name) {
                const categoryPrefixes = {
                    'barge': 'BARGE',
                    'tugboat': 'TUG',
                    'pusher': 'PUSHER',
                    'self_propelled': 'SELF',
                    'mixed': 'MIXED'
                };
                
                const prefix = categoryPrefixes[category] || 'VESSEL';
                const nameCode = name.toUpperCase()
                                   .replace(/[^A-Z0-9\s]/g, '')
                                   .split(' ')
                                   .slice(0, 2)
                                   .join('_');
                
                const timestamp = Date.now().toString().slice(-3);
                const generatedCode = `${prefix}_${nameCode}_${timestamp}`;
                
                if (!codeInput.value || codeInput.value.startsWith(categoryPrefixes[category] || 'VESSEL')) {
                    codeInput.value = generatedCode;
                }
            }
        }

        categorySelect.addEventListener('change', generateCode);
        nameInput.addEventListener('blur', generateCode);

        // Validación de rangos
        function validateRange(minInput, maxInput, fieldName) {
            minInput.addEventListener('change', function() {
                const minVal = parseFloat(this.value);
                const maxVal = parseFloat(maxInput.value);
                
                if (minVal && maxVal && minVal > maxVal) {
                    alert(`El valor mínimo de ${fieldName} no puede ser mayor al máximo`);
                    this.focus();
                }
            });

            maxInput.addEventListener('change', function() {
                const minVal = parseFloat(minInput.value);
                const maxVal = parseFloat(this.value);
                
                if (minVal && maxVal && maxVal < minVal) {
                    alert(`El valor máximo de ${fieldName} no puede ser menor al mínimo`);
                    this.focus();
                }
            });
        }

        // Aplicar validación de rangos
        validateRange(document.getElementById('min_length'), document.getElementById('max_length'), 'longitud');
        validateRange(document.getElementById('min_beam'), document.getElementById('max_beam'), 'manga');
        validateRange(document.getElementById('min_draft'), document.getElementById('max_draft'), 'calado');
        validateRange(document.getElementById('min_cargo_capacity'), document.getElementById('max_cargo_capacity'), 'capacidad de carga');
        validateRange(document.getElementById('min_container_capacity'), document.getElementById('max_container_capacity'), 'capacidad de contenedores');
        validateRange(document.getElementById('min_liquid_capacity'), document.getElementById('max_liquid_capacity'), 'capacidad de líquidos');
        validateRange(document.getElementById('typical_crew_size'), document.getElementById('max_crew_size'), 'tripulación');
        validateRange(document.getElementById('typical_speed'), document.getElementById('max_speed'), 'velocidad');

        // Mostrar/ocultar campos según tipo de carga
        const handleLiquidCargo = document.getElementById('handles_liquid_cargo');
        const liquidCapacityFields = document.querySelectorAll('#min_liquid_capacity, #max_liquid_capacity');
        const imdgField = document.getElementById('imdg_class');

        function toggleLiquidFields() {
            const showLiquid = handleLiquidCargo.checked;
            liquidCapacityFields.forEach(field => {
                field.closest('.lg\\:col-span-1, div').style.display = showLiquid ? 'block' : 'none';
            });
        }

        handleLiquidCargo.addEventListener('change', toggleLiquidFields);
        toggleLiquidFields(); // Inicializar

        // Validación de formulario antes de enviar
        document.getElementById('vessel-type-form').addEventListener('submit', function(e) {
            const requiredFields = ['code', 'name', 'category', 'propulsion_type'];
            let hasErrors = false;

            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    hasErrors = true;
                    field.classList.add('border-red-500');
                    field.focus();
                }
            });

            if (hasErrors) {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios marcados con *');
            }
        });

        // Limpiar estilos de error al escribir
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('border-red-500');
            });
        });
    });
    </script>
</x-app-layout>