{{-- resources/views/company/shipments/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Crear Nueva Carga (Shipment)') }}
            </h2>
            <a href="{{ route('company.shipments.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Volver a Lista
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('company.shipments.store') }}" class="space-y-6">
                @csrf

                {{-- Información del Viaje --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Información del Viaje
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Seleccionar Viaje -->
                            <div class="sm:col-span-2">
                                <label for="voyage_id" class="block text-sm font-medium text-gray-700">
                                    Viaje <span class="text-red-500">*</span>
                                </label>
                                <select name="voyage_id" 
                                        id="voyage_id" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('voyage_id') border-red-300 @enderror">
                                    <option value="">Seleccione un viaje</option>
                                    @foreach($voyages as $voyage)
                                        <option value="{{ $voyage['id'] }}" 
                                                {{ (old('voyage_id', $selectedVoyageId) == $voyage['id']) ? 'selected' : '' }}
                                                data-shipments-count="{{ $voyage['current_shipments_count'] }}">
                                            {{ $voyage['display_name'] }} ({{ $voyage['current_shipments_count'] }} shipments)
                                        </option>
                                    @endforeach
                                </select>
                                @error('voyage_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">Solo se muestran viajes en estado de planificación, preparación o carga.</p>
                            </div>

                            <!-- Número de Shipment -->
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

                            <!-- Secuencia en Viaje (auto-calculada) -->
                            <div>
                                <label for="sequence_display" class="block text-sm font-medium text-gray-700">
                                    Secuencia en Viaje
                                </label>
                                <input type="text" 
                                       id="sequence_display" 
                                       readonly
                                       value="Se calculará automáticamente"
                                       class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm sm:text-sm">
                                <p class="mt-1 text-sm text-gray-500">La secuencia se asigna automáticamente según el orden de creación.</p>
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
                            <!-- Embarcación -->
                            <div class="sm:col-span-2">
                                <label for="vessel_id" class="block text-sm font-medium text-gray-700">
                                    Embarcación <span class="text-red-500">*</span>
                                </label>
                                <select name="vessel_id" 
                                        id="vessel_id" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('vessel_id') border-red-300 @enderror">
                                    <option value="">Seleccione una embarcación</option>
                                    @foreach($vessels as $vessel)
                                        <option value="{{ $vessel['id'] }}" 
                                                {{ old('vessel_id') == $vessel['id'] ? 'selected' : '' }}
                                                data-cargo-capacity="{{ $vessel['cargo_capacity_tons'] }}"
                                                data-container-capacity="{{ $vessel['container_capacity'] }}">
                                            {{ $vessel['display_name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vessel_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Capitán -->
                            <div class="sm:col-span-2">
                                <label for="captain_id" class="block text-sm font-medium text-gray-700">
                                    Capitán
                                </label>
                                <select name="captain_id" 
                                        id="captain_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('captain_id') border-red-300 @enderror">
                                    <option value="">Seleccione un capitán (opcional)</option>
                                    @foreach($captains as $captain)
                                        <option value="{{ $captain['id'] }}" {{ old('captain_id') == $captain['id'] ? 'selected' : '' }}>
                                            {{ $captain['display_name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('captain_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">El capitán puede asignarse posteriormente si no está disponible ahora.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Configuración de Convoy --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Configuración de Convoy
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Rol de la Embarcación -->
                            <div>
                                <label for="vessel_role" class="block text-sm font-medium text-gray-700">
                                    Rol en el Convoy <span class="text-red-500">*</span>
                                </label>
                                <select name="vessel_role" 
                                        id="vessel_role" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('vessel_role') border-red-300 @enderror">
                                    @foreach($vesselRoles as $value => $label)
                                        <option value="{{ $value }}" {{ old('vessel_role', 'single') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vessel_role')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Posición en Convoy -->
                            <div>
                                <label for="convoy_position" class="block text-sm font-medium text-gray-700">
                                    Posición en Convoy
                                </label>
                                <input type="number" 
                                       name="convoy_position" 
                                       id="convoy_position" 
                                       value="{{ old('convoy_position') }}"
                                       min="1"
                                       max="99"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('convoy_position') border-red-300 @enderror">
                                @error('convoy_position')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">Posición en la formación del convoy (1, 2, 3...). Solo requerido para convoy.</p>
                            </div>

                            <!-- Es Embarcación Líder -->
                            <div class="sm:col-span-2">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           name="is_lead_vessel" 
                                           id="is_lead_vessel"
                                           value="1"
                                           {{ old('is_lead_vessel') ? 'checked' : '' }}
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="is_lead_vessel" class="ml-2 block text-sm text-gray-900">
                                        Es embarcación líder del convoy
                                    </label>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Marque si esta embarcación lidera las operaciones del convoy.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Capacidades de Carga --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Capacidades de Carga
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Capacidad de Carga -->
                            <div>
                                <label for="cargo_capacity_tons" class="block text-sm font-medium text-gray-700">
                                    Capacidad de Carga (Toneladas) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       name="cargo_capacity_tons" 
                                       id="cargo_capacity_tons" 
                                       value="{{ old('cargo_capacity_tons') }}"
                                       step="0.01"
                                       min="0"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('cargo_capacity_tons') border-red-300 @enderror">
                                @error('cargo_capacity_tons')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Capacidad de Contenedores -->
                            <div>
                                <label for="container_capacity" class="block text-sm font-medium text-gray-700">
                                    Capacidad de Contenedores <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       name="container_capacity" 
                                       id="container_capacity" 
                                       value="{{ old('container_capacity') }}"
                                       min="0"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('container_capacity') border-red-300 @enderror">
                                @error('container_capacity')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4 p-4 bg-blue-50 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        <strong>Tip:</strong> Estas capacidades se cargarán automáticamente desde los datos de la embarcación seleccionada, pero pueden ser ajustadas para este viaje específico.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Estado y Observaciones --}}
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Estado y Observaciones
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Estado Inicial -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado Inicial <span class="text-red-500">*</span>
                                </label>
                                <select name="status" 
                                        id="status" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('status') border-red-300 @enderror">
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('status', 'planning') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div></div> {{-- Espacio vacío para layout --}}

                            <!-- Instrucciones Especiales -->
                            <div class="sm:col-span-2">
                                <label for="special_instructions" class="block text-sm font-medium text-gray-700">
                                    Instrucciones Especiales
                                </label>
                                <textarea name="special_instructions" 
                                          id="special_instructions" 
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('special_instructions') border-red-300 @enderror">{{ old('special_instructions') }}</textarea>
                                @error('special_instructions')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">Instrucciones particulares para el manejo de esta carga.</p>
                            </div>

                            <!-- Notas de Manejo -->
                            <div class="sm:col-span-2">
                                <label for="handling_notes" class="block text-sm font-medium text-gray-700">
                                    Notas de Manejo
                                </label>
                                <textarea name="handling_notes" 
                                          id="handling_notes" 
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('handling_notes') border-red-300 @enderror">{{ old('handling_notes') }}</textarea>
                                @error('handling_notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">Notas operativas para el personal de carga.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('company.shipments.index') }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Crear Carga
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- JavaScript para funcionalidad dinámica --}}
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const vesselSelect = document.getElementById('vessel_id');
            const cargoCapacityInput = document.getElementById('cargo_capacity_tons');
            const containerCapacityInput = document.getElementById('container_capacity');
            const vesselRoleSelect = document.getElementById('vessel_role');
            const convoyPositionInput = document.getElementById('convoy_position');
            const isLeadVesselCheckbox = document.getElementById('is_lead_vessel');

            // Auto-completar capacidades cuando se selecciona una embarcación
            vesselSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const cargoCapacity = selectedOption.dataset.cargoCapacity || '';
                    const containerCapacity = selectedOption.dataset.containerCapacity || '';
                    
                    if (cargoCapacity && !cargoCapacityInput.value) {
                        cargoCapacityInput.value = cargoCapacity;
                    }
                    
                    if (containerCapacity && !containerCapacityInput.value) {
                        containerCapacityInput.value = containerCapacity;
                    }
                }
            });

            // Manejar lógica de convoy
            vesselRoleSelect.addEventListener('change', function() {
                const isSingle = this.value === 'single';
                
                convoyPositionInput.disabled = isSingle;
                if (isSingle) {
                    convoyPositionInput.value = '';
                    isLeadVesselCheckbox.checked = false;
                }
            });

            // Auto-marcar como líder si se selecciona rol lead
            vesselRoleSelect.addEventListener('change', function() {
                if (this.value === 'lead') {
                    isLeadVesselCheckbox.checked = true;
                } else if (this.value !== 'single') {
                    isLeadVesselCheckbox.checked = false;
                }
            });
        });
    </script>
    @endpush
</x-app-layout>