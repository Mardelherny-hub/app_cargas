<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nuevo Viaje Completo - Paso 1') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8" x-data="voyageWizardStep1()">
            
            {{-- HEADER DEL WIZARD --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">Nuevo Viaje Completo</h1>
                            <p class="text-sm text-gray-600">Captura todos los datos requeridos por AFIP</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                PASO 1 de 3
                            </span>
                        </div>
                    </div>
                    
                    {{-- RESTO DEL CONTENIDO IGUAL... --}}
                    {{-- (mantienes toda la barra de progreso y formulario igual) --}}
                </div>
            </div>

             {{-- BARRA DE PROGRESO --}}
                <div class="mt-4">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <div class="flex items-center">
                                {{-- Paso 1 - Activo --}}
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                        <span class="text-white text-sm font-medium">1</span>
                                    </div>
                                    <span class="ml-2 text-sm font-medium text-blue-600">Datos del Viaje</span>
                                </div>
                                
                                {{-- Línea conectora --}}
                                <div class="flex-1 mx-4 h-0.5 bg-gray-300"></div>
                                
                                {{-- Paso 2 - Pendiente --}}
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                        <span class="text-gray-500 text-sm font-medium">2</span>
                                    </div>
                                    <span class="ml-2 text-sm text-gray-500">Conocimientos</span>
                                </div>
                                
                                {{-- Línea conectora --}}
                                <div class="flex-1 mx-4 h-0.5 bg-gray-300"></div>
                                
                                {{-- Paso 3 - Pendiente --}}
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                        <span class="text-gray-500 text-sm font-medium">3</span>
                                    </div>
                                    <span class="ml-2 text-sm text-gray-500">Mercadería</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Barra de progreso numérica --}}
                    <div class="mt-2">
                        <div class="bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 33.33%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Progreso: 33% completado</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- FORMULARIO PRINCIPAL --}}
                <form action="{{ route('voyage-wizard.store-step1') }}" method="POST" class="space-y-6">
            @csrf
            
            {{-- DATOS BÁSICOS DEL VIAJE --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información Básica del Viaje</h3>
                    <p class="text-sm text-gray-600">Datos principales que identifican el viaje</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Número de Viaje --}}
                        <div>
                            <label for="voyage_number" class="block text-sm font-medium text-gray-700 mb-1">
                                Número de Viaje <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="voyage_number" 
                                   name="voyage_number" 
                                   value="{{ old('voyage_number', $savedData['voyage_number'] ?? '') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('voyage_number') border-red-300 @enderror"
                                   placeholder="ej: V024NB"
                                   required>
                            @error('voyage_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Referencia Interna --}}
                        <div>
                            <label for="internal_reference" class="block text-sm font-medium text-gray-700 mb-1">
                                Referencia Interna
                            </label>
                            <input type="text" 
                                   id="internal_reference" 
                                   name="internal_reference" 
                                   value="{{ old('internal_reference', $savedData['internal_reference'] ?? '') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Referencia opcional">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Fecha de Salida --}}
                        <div>
                            <label for="departure_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Fecha de Salida <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   id="departure_date" 
                                   name="departure_date" 
                                   value="{{ old('departure_date', $savedData['departure_date'] ?? '') }}"
                                   min="{{ date('Y-m-d') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('departure_date') border-red-300 @enderror"
                                   required>
                            @error('departure_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Fecha de Llegada Estimada --}}
                        <div>
                            <label for="estimated_arrival_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Fecha de Llegada Estimada <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   id="estimated_arrival_date" 
                                   name="estimated_arrival_date" 
                                   value="{{ old('estimated_arrival_date', $savedData['estimated_arrival_date'] ?? '') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('estimated_arrival_date') border-red-300 @enderror"
                                   required>
                            @error('estimated_arrival_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- RUTA DEL VIAJE --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Ruta del Viaje</h3>
                    <p class="text-sm text-gray-600">Puertos de origen y destino</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- ORIGEN --}}
                        <div class="space-y-4">
                            <h4 class="text-sm font-medium text-gray-900 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                Puerto de Origen
                            </h4>
                            
                            <div>
                                <label for="origin_country_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    País de Origen <span class="text-red-500">*</span>
                                </label>
                                <select id="origin_country_id" 
                                        name="origin_country_id" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('origin_country_id') border-red-300 @enderror"
                                        @change="loadPortsByCountry('origin', $event.target.value)"
                                        required>
                                    <option value="">Seleccionar país...</option>
                                    @foreach($formData['countries'] as $country)
                                        <option value="{{ $country->id }}" 
                                                {{ old('origin_country_id', $savedData['origin_country_id'] ?? '') == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }} ({{ $country->iso_code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('origin_country_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="origin_port_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Puerto de Origen <span class="text-red-500">*</span>
                                </label>
                                <select id="origin_port_id" 
                                        name="origin_port_id" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('origin_port_id') border-red-300 @enderror"
                                        required>
                                    <option value="">Primero seleccione el país...</option>
                                </select>
                                @error('origin_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- DESTINO --}}
                        <div class="space-y-4">
                            <h4 class="text-sm font-medium text-gray-900 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Puerto de Destino
                            </h4>
                            
                            <div>
                                <label for="destination_country_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    País de Destino <span class="text-red-500">*</span>
                                </label>
                                <select id="destination_country_id" 
                                        name="destination_country_id" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('destination_country_id') border-red-300 @enderror"
                                        @change="loadPortsByCountry('destination', $event.target.value)"
                                        required>
                                    <option value="">Seleccionar país...</option>
                                    @foreach($formData['countries'] as $country)
                                        <option value="{{ $country->id }}" 
                                                {{ old('destination_country_id', $savedData['destination_country_id'] ?? '') == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }} ({{ $country->iso_code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('destination_country_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="destination_port_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Puerto de Destino <span class="text-red-500">*</span>
                                </label>
                                <select id="destination_port_id" 
                                        name="destination_port_id" 
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('destination_port_id') border-red-300 @enderror"
                                        required>
                                    <option value="">Primero seleccione el país...</option>
                                </select>
                                @error('destination_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- EMBARCACIÓN Y CAPITÁN --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Embarcación y Capitán</h3>
                    <p class="text-sm text-gray-600">Seleccionar embarcación principal y capitán</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {{-- Embarcación Principal --}}
                        <div>
                            <label for="lead_vessel_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Embarcación Principal <span class="text-red-500">*</span>
                            </label>
                            <select id="lead_vessel_id" 
                                    name="lead_vessel_id" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('lead_vessel_id') border-red-300 @enderror"
                                    @change="onVesselSelected($event.target.value)"
                                    required>
                                <option value="">Seleccionar embarcación...</option>
                                @foreach($formData['vessels'] as $vessel)
                                    <option value="{{ $vessel->id }}" 
                                            data-captain="{{ $vessel->primaryCaptain?->id }}"
                                            {{ old('lead_vessel_id', $savedData['lead_vessel_id'] ?? '') == $vessel->id ? 'selected' : '' }}>
                                        {{ $vessel->name }}
                                        @if($vessel->imo_number) - IMO: {{ $vessel->imo_number }} @endif
                                        ({{ $vessel->vesselType?->name ?? 'Tipo no especificado' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('lead_vessel_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Capitán --}}
                        <div>
                            <label for="captain_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Capitán <span class="text-red-500">*</span>
                            </label>
                            <select id="captain_id" 
                                    name="captain_id" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('captain_id') border-red-300 @enderror"
                                    required>
                                <option value="">Seleccionar capitán...</option>
                                @foreach($formData['captains'] as $captain)
                                    <option value="{{ $captain->id }}" 
                                            {{ old('captain_id', $savedData['captain_id'] ?? '') == $captain->id ? 'selected' : '' }}>
                                        {{ $captain->full_name }}
                                        @if($captain->license_number) - Lic: {{ $captain->license_number }} @endif
                                        @if($captain->nationality) ({{ $captain->nationality }}) @endif
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

            {{-- CARACTERÍSTICAS DEL VIAJE --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Características del Viaje</h3>
                    <p class="text-sm text-gray-600">Tipo de operación y configuración</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {{-- Tipo de Viaje --}}
                        <div>
                            <label for="voyage_type" class="block text-sm font-medium text-gray-700 mb-1">
                                Tipo de Viaje <span class="text-red-500">*</span>
                            </label>
                            <select id="voyage_type" 
                                    name="voyage_type" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    @change="onVoyageTypeChanged($event.target.value)"
                                    required>
                                @foreach($formData['voyageTypes'] as $value => $label)
                                    <option value="{{ $value }}" 
                                            {{ old('voyage_type', $savedData['voyage_type'] ?? $formData['defaults']['voyage_type']) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tipo de Carga --}}
                        <div>
                            <label for="cargo_type" class="block text-sm font-medium text-gray-700 mb-1">
                                Tipo de Operación <span class="text-red-500">*</span>
                            </label>
                            <select id="cargo_type" 
                                    name="cargo_type" 
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    required>
                                @foreach($formData['cargoTypes'] as $value => $label)
                                    <option value="{{ $value }}" 
                                            {{ old('cargo_type', $savedData['cargo_type'] ?? $formData['defaults']['cargo_type']) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Cantidad de Embarcaciones --}}
                        <div>
                            <label for="vessel_count" class="block text-sm font-medium text-gray-700 mb-1">
                                Cantidad de Embarcaciones <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   id="vessel_count" 
                                   name="vessel_count" 
                                   value="{{ old('vessel_count', $savedData['vessel_count'] ?? $formData['defaults']['vessel_count']) }}"
                                   min="1" 
                                   max="20"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   @change="onVesselCountChanged($event.target.value)"
                                   required>
                        </div>
                    </div>

                    {{-- CHECKBOXES AFIP --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-blue-900 mb-3">Información AFIP Obligatoria</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Es Convoy --}}
                            <div class="flex items-center">
                                <input type="hidden" name="is_convoy" value="0">
                                <input type="checkbox" 
                                       id="is_convoy" 
                                       name="is_convoy" 
                                       value="1"
                                       {{ old('is_convoy', $savedData['is_convoy'] ?? $formData['defaults']['is_convoy']) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_convoy" class="ml-2 block text-sm text-gray-700">
                                    Es convoy (remolcador + barcazas)
                                </label>
                            </div>

                            {{-- Transporte Vacío --}}
                            <div class="flex items-center">
                                <input type="hidden" name="is_empty_transport" value="0">
                                <input type="checkbox" 
                                       id="is_empty_transport" 
                                       name="is_empty_transport" 
                                       value="1"
                                       {{ old('is_empty_transport', $savedData['is_empty_transport'] ?? $formData['defaults']['is_empty_transport']) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_empty_transport" class="ml-2 block text-sm text-gray-700">
                                    Transporte vacío (sin carga)
                                </label>
                            </div>

                            {{-- Tiene Carga a Bordo --}}
                            <div class="flex items-center">
                                <input type="hidden" name="has_cargo_onboard" value="0">
                                <input type="checkbox" 
                                       id="has_cargo_onboard" 
                                       name="has_cargo_onboard" 
                                       value="1"
                                       {{ old('has_cargo_onboard', $savedData['has_cargo_onboard'] ?? $formData['defaults']['has_cargo_onboard']) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="has_cargo_onboard" class="ml-2 block text-sm text-gray-700">
                                    Tiene carga a bordo
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- NOTAS OPCIONALES --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="special_instructions" class="block text-sm font-medium text-gray-700 mb-1">
                                Instrucciones Especiales
                            </label>
                            <textarea id="special_instructions" 
                                      name="special_instructions" 
                                      rows="3"
                                      class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                      placeholder="Instrucciones especiales para el viaje...">{{ old('special_instructions', $savedData['special_instructions'] ?? '') }}</textarea>
                        </div>

                        <div>
                            <label for="operational_notes" class="block text-sm font-medium text-gray-700 mb-1">
                                Notas Operacionales
                            </label>
                            <textarea id="operational_notes" 
                                      name="operational_notes" 
                                      rows="3"
                                      class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                      placeholder="Notas adicionales...">{{ old('operational_notes', $savedData['operational_notes'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BOTONES DE NAVEGACIÓN --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('voyage-wizard.cancel') }}" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Cancelar
                            </a>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button type="submit" 
                                    class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Siguiente: Conocimientos
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>

        </div>
    </div>

    {{-- SCRIPTS AL FINAL --}}
    <script>
    window.voyageWizardData = {
        ports: @json($formData['ports']),
        savedData: @json($savedData)
    };
    </script>

@push('scripts')
<script>
function voyageWizardStep1() {
    return {
        init() {
            // Cargar puertos si hay países seleccionados
            const originCountryId = document.getElementById('origin_country_id').value;
            const destCountryId = document.getElementById('destination_country_id').value;
            
            if (originCountryId) {
                this.loadPortsByCountry('origin', originCountryId);
            }
            if (destCountryId) {
                this.loadPortsByCountry('destination', destCountryId);
            }

            // Auto-seleccionar capitán si hay embarcación
            const vesselId = document.getElementById('lead_vessel_id').value;
            if (vesselId) {
                this.onVesselSelected(vesselId);
            }
        },

        

        loadPortsByCountry(type, countryId) {
            const selectElement = document.getElementById(`${type}_port_id`);
            selectElement.innerHTML = '<option value="">Cargando puertos...</option>';
            
            if (!countryId) {
                selectElement.innerHTML = '<option value="">Primero seleccione el país...</option>';
                return;
            }

            // 🔍 DEBUG: Verificar datos
    console.log('voyageWizardData:', window.voyageWizardData);
    console.log('ports disponibles:', window.voyageWizardData.ports);
    console.log('filtrando por country_id:', countryId);

            const ports = window.voyageWizardData.ports.filter(port => port.country_id == countryId);
            
            let html = '<option value="">Seleccionar puerto...</option>';
            
            const savedData = window.voyageWizardData.savedData || {};
            const savedPortId = savedData[`${type}_port_id`];
            
            ports.forEach(port => {
                const selected = savedPortId == port.id ? 'selected' : '';
                html += `<option value="${port.id}" ${selected}>${port.name} (${port.code})</option>`;
            });
            
            selectElement.innerHTML = html;
        },

        onVesselSelected(vesselId) {
            if (!vesselId) return;
            
            const vesselOption = document.querySelector(`#lead_vessel_id option[value="${vesselId}"]`);
            const captainId = vesselOption?.dataset.captain;
            
            if (captainId) {
                document.getElementById('captain_id').value = captainId;
            }
        },

        onVoyageTypeChanged(type) {
            const isConvoyCheckbox = document.getElementById('is_convoy');
            const vesselCountInput = document.getElementById('vessel_count');
            
            if (type === 'convoy') {
                isConvoyCheckbox.checked = true;
                vesselCountInput.value = Math.max(2, vesselCountInput.value);
            } else if (type === 'single_vessel') {
                isConvoyCheckbox.checked = false;
                vesselCountInput.value = 1;
            }
        },

        onVesselCountChanged(count) {
            const isConvoyCheckbox = document.getElementById('is_convoy');
            const voyageTypeSelect = document.getElementById('voyage_type');
            
            if (count > 1) {
                isConvoyCheckbox.checked = true;
                if (voyageTypeSelect.value === 'single_vessel') {
                    voyageTypeSelect.value = 'convoy';
                }
            } else {
                isConvoyCheckbox.checked = false;
                voyageTypeSelect.value = 'single_vessel';
            }
        }
    }
}
</script>
@endpush
</x-app-layout>