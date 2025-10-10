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
            {{-- mensajes de eror --}}
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">¡Error!</strong>
                    <ul class="list-disc pl-5 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
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

                {{-- Embarcación y Capitán --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Embarcación y Capitán
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Embarcación --}}
                            <div>
                                <label for="vessel_id" class="block text-sm font-medium text-gray-700">
                                    Embarcación <span class="text-red-500">*</span>
                                </label>
                                <select name="vessel_id" id="vessel_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Seleccione una embarcación</option>
                                    @foreach($formData['vessels'] as $vessel)
                                        <option value="{{ $vessel['id'] }}">{{ $vessel['display_name'] }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-sm text-gray-500">Seleccione la embarcación específica para este shipment.</p>
                            </div>

                            {{-- Capitán --}}
                            <div>
                                <label for="captain_id" class="block text-sm font-medium text-gray-700">
                                    Capitán
                                </label>
                                <select name="captain_id" id="captain_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Seleccione un capitán</option>
                                    @foreach($formData['captains'] as $captain)
                                        <option value="{{ $captain['id'] }}">{{ $captain['display_name'] }}</option>
                                    @endforeach
                                </select>
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

                {{-- ✅ NUEVO: Información de Trasbordo (Opcional) --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Información de Trasbordo
                            </h3>
                            <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Opcional - Solo para trasbordos
                            </span>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-4">
                            <div class="flex">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        Complete estos campos <strong>únicamente si este shipment corresponde a un trasbordo</strong> 
                                        (carga que viene de otro medio de transporte). Estos datos serán utilizados para 
                                        la declaración MIC/DTA ante AFIP.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- ID del Manifiesto de Origen --}}
                            <div>
                                <label for="origin_manifest_id" class="block text-sm font-medium text-gray-700">
                                    ID Manifiesto de Origen
                                </label>
                                <input type="text" 
                                       name="origin_manifest_id" 
                                       id="origin_manifest_id" 
                                       maxlength="20"
                                       value="{{ old('origin_manifest_id') }}"
                                       placeholder="Ej: MAN-2024-001234"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('origin_manifest_id') border-red-300 @enderror">
                                @error('origin_manifest_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">
                                    Identificador del manifiesto del transporte anterior (máx. 20 caracteres)
                                </p>
                            </div>

                            {{-- Documento de Transporte de Origen --}}
                            <div>
                                <label for="origin_transport_doc" class="block text-sm font-medium text-gray-700">
                                    Documento de Transporte de Origen
                                </label>
                                <input type="text" 
                                       name="origin_transport_doc" 
                                       id="origin_transport_doc" 
                                       maxlength="39"
                                       value="{{ old('origin_transport_doc') }}"
                                       placeholder="Ej: BL-ARG-2024-987654"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('origin_transport_doc') border-red-300 @enderror">
                                @error('origin_transport_doc')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">
                                    Número del documento de transporte anterior (BL, CRT, AWB, etc. - máx. 39 caracteres)
                                </p>
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