<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Crear Nueva Carga
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('company.voyages.show', $voyage) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Volver al Viaje
                </a>
                <a href="{{ route('company.shipments.index') }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Lista de Cargas
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Información del Viaje (Heredada) --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-medium text-blue-900 mb-4">
                    📦 Datos del Viaje: {{ $voyage->voyage_number }}
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm font-medium text-blue-700">Embarcación</p>
                        <p class="text-sm text-blue-600">{{ $formData['voyageInfo']['vessel_name'] }}</p>
                        <p class="text-xs text-blue-500">{{ $formData['voyageInfo']['vessel_type'] }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm font-medium text-blue-700">Capitán</p>
                        <p class="text-sm text-blue-600">{{ $formData['voyageInfo']['captain_name'] }}</p>
                        <p class="text-xs text-blue-500">Lic: {{ $formData['voyageInfo']['captain_license'] }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm font-medium text-blue-700">Capacidad Carga</p>
                        <p class="text-sm text-blue-600">{{ number_format($formData['voyageInfo']['cargo_capacity'], 2) }} Tons</p>
                    </div>
                    
                    <div>
                        <p class="text-sm font-medium text-blue-700">Capacidad Contenedores</p>
                        <p class="text-sm text-blue-600">{{ $formData['voyageInfo']['container_capacity'] }} TEU</p>
                    </div>
                </div>
                
                <div class="mt-3 p-3 bg-blue-100 rounded-md">
                    <p class="text-sm text-blue-800">
                        ✅ La embarcación y capitán se asignaronn al iniciar el viaje.
                         Solo necesitas configurar los datos específicos del shipment.
                    </p>
                </div>
            </div>

            {{-- Formulario Simplificado --}}
            <form method="POST" action="{{ route('company.shipments.store') }}" class="space-y-6">
                @csrf
                
                {{-- Campo oculto para voyage_id --}}
                <input type="hidden" name="voyage_id" value="{{ $voyage->id }}">
                
                {{-- Información Básica del Shipment --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Información Básica del Shipment
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Viaje (Solo lectura) --}}
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Viaje</label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                        🚢
                                    </span>
                                    <input type="text" 
                                           readonly
                                           value="{{ $voyage->voyage_number }} ({{ $voyage->originPort->name ?? 'N/A' }} → {{ $voyage->destinationPort->name ?? 'N/A' }})"
                                           class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                </div>
                                <p class="mt-1 text-sm text-gray-500">El viaje está predefinido y no puede cambiarse.</p>
                            </div>

                            {{-- Número de Shipment --}}
                            <div>
                                <label for="shipment_number" class="block text-sm font-medium text-gray-700">
                                    Número de Carga <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="shipment_number" 
                                       id="shipment_number" 
                                       value="{{ old('shipment_number', $nextShipmentNumber) }}"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('shipment_number') border-red-300 @enderror">
                                @error('shipment_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Secuencia (Auto-calculada) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Secuencia en Viaje</label>
                                <input type="text" 
                                       readonly
                                       value="Se calculará automáticamente"
                                       class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm sm:text-sm">
                                <p class="mt-1 text-sm text-gray-500">La secuencia se asigna según el orden de creación.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Configuración de Convoy (Simplificada) --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Configuración de Convoy
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Rol de la Embarcación --}}
                            <div>
                                <label for="vessel_role" class="block text-sm font-medium text-gray-700">
                                    Rol en el Convoy <span class="text-red-500">*</span>
                                </label>
                                <select name="vessel_role" 
                                        id="vessel_role" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('vessel_role') border-red-300 @enderror">
                                    @foreach($formData['vesselRoles'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('vessel_role', 'single') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vessel_role')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Posición en Convoy --}}
                            <div id="convoy-position-field" style="display: none;">
                                <label for="convoy_position" class="block text-sm font-medium text-gray-700">
                                    Posición en Convoy
                                </label>
                                <input type="number" 
                                       name="convoy_position" 
                                       id="convoy_position" 
                                       min="1" 
                                       max="10"
                                       value="{{ old('convoy_position') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('convoy_position') border-red-300 @enderror">
                                @error('convoy_position')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">Posición en la formación del convoy (1-10).</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Capacidades --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Capacidades de Carga
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Capacidad de Carga --}}
                            <div>
                                <label for="cargo_capacity_tons" class="block text-sm font-medium text-gray-700">
                                    Capacidad de Carga (Toneladas) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       name="cargo_capacity_tons" 
                                       id="cargo_capacity_tons" 
                                       step="0.01"
                                       min="0.01"
                                       value="{{ old('cargo_capacity_tons', $formData['voyageInfo']['cargo_capacity']) }}"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('cargo_capacity_tons') border-red-300 @enderror">
                                @error('cargo_capacity_tons')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">Valor predefinido desde la embarcación del viaje.</p>
                            </div>

                            {{-- Capacidad de Contenedores --}}
                            <div>
                                <label for="container_capacity" class="block text-sm font-medium text-gray-700">
                                    Capacidad de Contenedores (TEU)
                                </label>
                                <input type="number" 
                                       name="container_capacity" 
                                       id="container_capacity" 
                                       min="0"
                                       value="{{ old('container_capacity', $formData['voyageInfo']['container_capacity']) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('container_capacity') border-red-300 @enderror">
                                @error('container_capacity')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">Valor predefinido desde la embarcación del viaje.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Estado e Instrucciones --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Estado e Instrucciones
                        </h3>

                        <div class="grid grid-cols-1 gap-6">
                            {{-- Estado --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado Inicial <span class="text-red-500">*</span>
                                </label>
                                <select name="status" 
                                        id="status" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('status') border-red-300 @enderror">
                                    @foreach($formData['statusOptions'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('status', 'planning') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Instrucciones Especiales --}}
                            <div>
                                <label for="special_instructions" class="block text-sm font-medium text-gray-700">
                                    Instrucciones Especiales
                                </label>
                                <textarea name="special_instructions" 
                                          id="special_instructions" 
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('special_instructions') border-red-300 @enderror"
                                          placeholder="Instrucciones específicas para este shipment...">{{ old('special_instructions') }}</textarea>
                                @error('special_instructions')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Notas de Manejo --}}
                            <div>
                                <label for="handling_notes" class="block text-sm font-medium text-gray-700">
                                    Notas de Manejo
                                </label>
                                <textarea name="handling_notes" 
                                          id="handling_notes" 
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('handling_notes') border-red-300 @enderror"
                                          placeholder="Notas operativas para el manejo de la carga...">{{ old('handling_notes') }}</textarea>
                                @error('handling_notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('company.voyages.show', $voyage) }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Crear Shipment
                    </button>
                </div>
            </form>
        </div>
    </div>


</x-app-layout>