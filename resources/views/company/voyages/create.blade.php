<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.voyages.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Crear Nuevo Viaje
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('company.voyages.store') }}" class="space-y-6">
                @csrf

                <!-- Información General -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Información General
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Número de Viaje -->
                            <div>
                                <label for="voyage_number" class="block text-sm font-medium text-gray-700">
                                    Número de Viaje <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="voyage_number" 
                                       id="voyage_number" 
                                       value="{{ old('voyage_number') }}"
                                       required
                                       maxlength="50"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('voyage_number') border-red-300 @enderror"
                                       placeholder="Ej: V001-2025">
                                @error('voyage_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Referencia Interna -->
                            <div>
                                <label for="internal_reference" class="block text-sm font-medium text-gray-700">
                                    Referencia Interna
                                </label>
                                <input type="text" 
                                       name="internal_reference" 
                                       id="internal_reference" 
                                       value="{{ old('internal_reference') }}"
                                       maxlength="100"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('internal_reference') border-red-300 @enderror"
                                       placeholder="Referencia opcional">
                                @error('internal_reference')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Viaje -->
                            <div>
                                <label for="voyage_type" class="block text-sm font-medium text-gray-700">
                                    Tipo de Viaje <span class="text-red-500">*</span>
                                </label>
                                <select name="voyage_type" 
                                        id="voyage_type" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('voyage_type') border-red-300 @enderror">
                                    <option value="">Seleccione un tipo</option>
                                    <option value="regular" {{ old('voyage_type') == 'regular' ? 'selected' : '' }}>Regular</option>
                                    <option value="charter" {{ old('voyage_type') == 'charter' ? 'selected' : '' }}>Charter</option>
                                    <option value="emergency" {{ old('voyage_type') == 'emergency' ? 'selected' : '' }}>Emergencia</option>
                                </select>
                                @error('voyage_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Carga -->
                            <div>
                                <label for="cargo_type" class="block text-sm font-medium text-gray-700">
                                    Tipo de Carga <span class="text-red-500">*</span>
                                </label>
                                <select name="cargo_type" 
                                        id="cargo_type" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('cargo_type') border-red-300 @enderror">
                                    <option value="">Seleccione tipo de carga</option>
                                    <option value="containers" {{ old('cargo_type') == 'containers' ? 'selected' : '' }}>Contenedores</option>
                                    <option value="bulk" {{ old('cargo_type') == 'bulk' ? 'selected' : '' }}>Granel</option>
                                    <option value="general" {{ old('cargo_type') == 'general' ? 'selected' : '' }}>General</option>
                                    <option value="liquid" {{ old('cargo_type') == 'liquid' ? 'selected' : '' }}>Líquida</option>
                                </select>
                                @error('cargo_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Embarcación y Capitán -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Embarcación y Capitán
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Embarcación Líder -->
                            <div>
                                <label for="lead_vessel_id" class="block text-sm font-medium text-gray-700">
                                    Embarcación Líder <span class="text-red-500">*</span>
                                </label>
                                <select name="lead_vessel_id" 
                                        id="lead_vessel_id" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('lead_vessel_id') border-red-300 @enderror">
                                    <option value="">Seleccione una embarcación</option>
                                    @foreach($vessels as $vessel)
                                        <option value="{{ $vessel->id }}" {{ old('lead_vessel_id') == $vessel->id ? 'selected' : '' }}>
                                            {{ $vessel->name }}
                                            @if($vessel->vesselType)
                                                - {{ $vessel->vesselType->name }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('lead_vessel_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Capitán -->
                            <div>
                                <label for="captain_id" class="block text-sm font-medium text-gray-700">
                                    Capitán
                                </label>
                                <select name="captain_id" 
                                        id="captain_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('captain_id') border-red-300 @enderror">
                                    <option value="">Seleccione un capitán (opcional)</option>
                                    @foreach($captains as $captain)
                                        <option value="{{ $captain->id }}" {{ old('captain_id') == $captain->id ? 'selected' : '' }}>
                                            {{ $captain->full_name }} - Lic: {{ $captain->license_number }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('captain_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Ruta -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Información de Ruta
                        </h3>

                        <!-- Origen -->
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-800 mb-3">Origen</h4>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="origin_country_id" class="block text-sm font-medium text-gray-700">
                                        País de Origen <span class="text-red-500">*</span>
                                    </label>
                                    <select name="origin_country_id" 
                                            id="origin_country_id" 
                                            required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('origin_country_id') border-red-300 @enderror">
                                        <option value="">Seleccione país</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}" {{ old('origin_country_id') == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }} ({{ $country->iso_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('origin_country_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="origin_port_id" class="block text-sm font-medium text-gray-700">
                                        Puerto de Origen <span class="text-red-500">*</span>
                                    </label>
                                    <select name="origin_port_id" 
                                            id="origin_port_id" 
                                            required
                                            disabled
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('origin_port_id') border-red-300 @enderror">
                                        <option value="">Primero seleccione un país</option>
                                    </select>
                                    @error('origin_port_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Destino -->
                        <div>
                            <h4 class="text-md font-medium text-gray-800 mb-3">Destino</h4>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="destination_country_id" class="block text-sm font-medium text-gray-700">
                                        País de Destino <span class="text-red-500">*</span>
                                    </label>
                                    <select name="destination_country_id" 
                                            id="destination_country_id" 
                                            required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('destination_country_id') border-red-300 @enderror">
                                        <option value="">Seleccione país</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}" {{ old('destination_country_id') == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }} ({{ $country->iso_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('destination_country_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="destination_port_id" class="block text-sm font-medium text-gray-700">
                                        Puerto de Destino <span class="text-red-500">*</span>
                                    </label>
                                    <select name="destination_port_id" 
                                            id="destination_port_id" 
                                            required
                                            disabled
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('destination_port_id') border-red-300 @enderror">
                                        <option value="">Primero seleccione un país</option>
                                    </select>
                                    @error('destination_port_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fechas -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Fechas del Viaje
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Fecha de Salida -->
                            <div>
                                <label for="departure_date" class="block text-sm font-medium text-gray-700">
                                    Fecha y Hora de Salida <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local" 
                                       name="departure_date" 
                                       id="departure_date" 
                                       value="{{ old('departure_date') }}"
                                       required
                                       min="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('departure_date') border-red-300 @enderror">
                                @error('departure_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Fecha de Llegada Estimada -->
                            <div>
                                <label for="estimated_arrival_date" class="block text-sm font-medium text-gray-700">
                                    Fecha y Hora Estimada de Llegada <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local" 
                                       name="estimated_arrival_date" 
                                       id="estimated_arrival_date" 
                                       value="{{ old('estimated_arrival_date') }}"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('estimated_arrival_date') border-red-300 @enderror">
                                @error('estimated_arrival_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center justify-end space-x-4">
                    <a href="{{ route('company.voyages.index') }}" 
                       class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Crear Viaje
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Datos de puertos agrupados por país desde el controlador
        const portsByCountry = @json($portsByCountry);
        
        // Referencias a los elementos del DOM
        const originCountrySelect = document.getElementById('origin_country_id');
        const originPortSelect = document.getElementById('origin_port_id');
        const destinationCountrySelect = document.getElementById('destination_country_id');
        const destinationPortSelect = document.getElementById('destination_port_id');
        
        // Función para actualizar puertos según país seleccionado
        function updatePortOptions(countryId, portSelect) {
            // Limpiar opciones actuales
            portSelect.innerHTML = '<option value="">Seleccione un puerto</option>';
            
            if (countryId && portsByCountry[countryId]) {
                // Agregar puertos del país seleccionado
                portsByCountry[countryId].forEach(function(port) {
                    const option = document.createElement('option');
                    option.value = port.id;
                    option.textContent = `${port.name} (${port.code}) - ${port.city}`;
                    
                    // Mantener selección previa si existe
                    if (port.id == portSelect.dataset.oldValue) {
                        option.selected = true;
                    }
                    
                    portSelect.appendChild(option);
                });
                
                portSelect.disabled = false;
            } else {
                portSelect.innerHTML = '<option value="">Primero seleccione un país</option>';
                portSelect.disabled = true;
            }
        }
        
        // Event listeners para cambios de país
        originCountrySelect.addEventListener('change', function() {
            updatePortOptions(this.value, originPortSelect);
        });
        
        destinationCountrySelect.addEventListener('change', function() {
            updatePortOptions(this.value, destinationPortSelect);
        });
        
        // Mantener valores seleccionados después de errores de validación
        const oldOriginPort = '{{ old("origin_port_id") }}';
        const oldDestinationPort = '{{ old("destination_port_id") }}';
        
        if (oldOriginPort) {
            originPortSelect.dataset.oldValue = oldOriginPort;
            if (originCountrySelect.value) {
                updatePortOptions(originCountrySelect.value, originPortSelect);
            }
        }
        
        if (oldDestinationPort) {
            destinationPortSelect.dataset.oldValue = oldDestinationPort;
            if (destinationCountrySelect.value) {
                updatePortOptions(destinationCountrySelect.value, destinationPortSelect);
            }
        }
        
        // Inicializar puertos si hay países preseleccionados
        if (originCountrySelect.value) {
            updatePortOptions(originCountrySelect.value, originPortSelect);
        }
        
        if (destinationCountrySelect.value) {
            updatePortOptions(destinationCountrySelect.value, destinationPortSelect);
        }
        
        // === VALIDACIÓN DE FECHAS ===
        const departureInput = document.getElementById('departure_date');
        const arrivalInput = document.getElementById('estimated_arrival_date');

        function validateDates() {
            if (departureInput.value && arrivalInput.value) {
                const departure = new Date(departureInput.value);
                const arrival = new Date(arrivalInput.value);
                
                if (arrival <= departure) {
                    arrivalInput.setCustomValidity('La fecha de llegada debe ser posterior a la fecha de salida');
                } else {
                    arrivalInput.setCustomValidity('');
                }
            }
        }

        departureInput.addEventListener('change', validateDates);
        arrivalInput.addEventListener('change', validateDates);

        // Auto-completar fecha de llegada (+2 días por defecto)
        departureInput.addEventListener('change', function() {
            if (this.value && !arrivalInput.value) {
                const departure = new Date(this.value);
                const arrival = new Date(departure);
                arrival.setDate(arrival.getDate() + 2);
                
                arrivalInput.value = arrival.toISOString().slice(0, 16);
            }
        });
    });
    </script>
    @endpush
</x-app-layout>