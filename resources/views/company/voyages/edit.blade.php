@php
    $user = Auth::user();
    $company = null;
    $companyRoles = [];
    
    if ($user) {
        if ($user->userable_type === 'App\\Models\\Company') {
            $company = $user->userable;
            $companyRoles = $company->company_roles ?? [];
        } elseif ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
            $company = $user->userable->company;
            $companyRoles = $company->company_roles ?? [];
        }
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.voyages.show', $voyage) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Editar Viaje {{ $voyage->voyage_number }}
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        @if(in_array('Cargas', $companyRoles))
                            Modificar información del viaje y coordinar operaciones de transporte
                        @else
                            Consultar información del viaje
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                @if(method_exists($voyage, 'canBeDeleted') && $voyage->canBeDeleted() && $userPermissions['can_delete'])
                    <button type="button" 
                            onclick="confirmDelete()"
                            class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Eliminar
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Mensajes Flash -->
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('warning') }}</span>
                </div>
            @endif

            <!-- Errores de Validación -->
            @if ($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">¡Errores de validación!</strong>
                    <ul class="mt-2 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('company.voyages.update', $voyage) }}" class="space-y-6" id="voyage-form">
                @csrf
                @method('PUT')

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
                                       value="{{ old('voyage_number', $voyage->voyage_number) }}"
                                       required
                                       maxlength="50"
                                       @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') readonly @endif
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('voyage_number') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif"
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
                                       value="{{ old('internal_reference', $voyage->internal_reference) }}"
                                       maxlength="100"
                                       @if(!$userPermissions['can_edit']) readonly @endif
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('internal_reference') border-red-300 @enderror @if(!$userPermissions['can_edit']) bg-gray-100 @endif"
                                       placeholder="Referencia opcional para uso interno">
                                @error('internal_reference')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estado -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado <span class="text-red-500">*</span>
                                </label>
                                <select name="status" 
                                        id="status" 
                                        required
                                        @if(!$userPermissions['can_edit']) disabled @endif
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('status') border-red-300 @enderror @if(!$userPermissions['can_edit']) bg-gray-100 @endif"
                                        onchange="console.log('Status selected:', this.value)">
                                    <option value="">Seleccione estado</option>
                                    @foreach([
                                        'planning' => 'En Planificación',
                                        'approved' => 'Aprobado', 
                                        'in_transit' => 'En Tránsito',
                                        'at_destination' => 'En Destino',
                                        'completed' => 'Completado',
                                        'cancelled' => 'Cancelado',
                                        'delayed' => 'Demorado'
                                    ] as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" {{ old('status', $voyage->status) === $statusKey ? 'selected' : '' }}>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="mt-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700">
                                Observaciones
                            </label>
                            <textarea name="notes" 
                                      id="notes" 
                                      rows="3"
                                      @if(!$userPermissions['can_edit']) readonly @endif
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('notes') border-red-300 @enderror @if(!$userPermissions['can_edit']) bg-gray-100 @endif"
                                      placeholder="Información adicional sobre el viaje">{{ old('notes', $voyage->notes) }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Información de Embarcación -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Información de Embarcación
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- Embarcación -->
                            <div>
                                <label for="vessel_id" class="block text-sm font-medium text-gray-700">
                                    Embarcación <span class="text-red-500">*</span>
                                </label>
                                <select name="vessel_id" 
                                        id="vessel_id" 
                                        required
                                        @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('vessel_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                    <option value="">Seleccione embarcación</option>
                                    @foreach($formData['vessels'] as $vessel)
                                        <option value="{{ $vessel->id }}" 
                                                data-capacity="{{ $vessel->cargo_capacity_tons }}"
                                                data-imo="{{ $vessel->imo_number }}"
                                                {{ old('vessel_id', $voyage->lead_vessel_id) == $vessel->id ? 'selected' : '' }}>
                                            {{ $vessel->name }} - {{ $vessel->imo_number }} ({{ number_format($vessel->cargo_capacity_tons, 0) }} Tons)
                                        </option>
                                    @endforeach
                                </select>
                                @error('vessel_id')
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
                                        @if(!$userPermissions['can_edit']) disabled @endif
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('captain_id') border-red-300 @enderror @if(!$userPermissions['can_edit']) bg-gray-100 @endif">
                                    <option value="">Sin capitán asignado</option>
                                    @foreach($formData['captains'] as $captain)
                                        <option value="{{ $captain->id }}" {{ old('captain_id', $voyage->captain_id) == $captain->id ? 'selected' : '' }}>
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
                                            @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('origin_country_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                        <option value="">Seleccione país</option>
                                        @foreach($formData['countries'] as $country)
                                            <option value="{{ $country->id }}" {{ old('origin_country_id', $voyage->origin_country_id) == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }}
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
                                            @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('origin_port_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                        <option value="">Seleccione puerto</option>
                                        @foreach($formData['ports'] as $port)
                                            <option value="{{ $port->id }}" 
                                                    data-country="{{ $port->country_id }}"
                                                    {{ old('origin_port_id', $voyage->origin_port_id) == $port->id ? 'selected' : '' }}>
                                                {{ $port->name }} ({{ $port->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('origin_port_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Destino -->
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-800 mb-3">Destino</h4>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="destination_country_id" class="block text-sm font-medium text-gray-700">
                                        País de Destino <span class="text-red-500">*</span>
                                    </label>
                                    <select name="destination_country_id" 
                                            id="destination_country_id" 
                                            required
                                            @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('destination_country_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                        <option value="">Seleccione país</option>
                                        @foreach($formData['countries'] as $country)
                                            <option value="{{ $country->id }}" {{ old('destination_country_id', $voyage->destination_country_id) == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }}
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
                                            @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('destination_port_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                        <option value="">Seleccione puerto</option>
                                        @foreach($formData['ports'] as $port)
                                            <option value="{{ $port->id }}" 
                                                    data-country="{{ $port->country_id }}"
                                                    {{ old('destination_port_id', $voyage->destination_port_id) == $port->id ? 'selected' : '' }}>
                                                {{ $port->name }} ({{ $port->code }})
                                            </option>
                                        @endforeach
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
                            Cronograma del Viaje
                        </h3>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <!-- Fecha de Salida Prevista -->
                            <div>
                                <label for="planned_departure_date" class="block text-sm font-medium text-gray-700">
                                    Salida Prevista <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local" 
                                       name="planned_departure_date" 
                                       id="planned_departure_date" 
                                       value="{{ old('planned_departure_date', $voyage->departure_date ? $voyage->departure_date->format('Y-m-d\TH:i') : '') }}"
                                       required
                                       @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') readonly @endif
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('planned_departure_date') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                @error('planned_departure_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Fecha de Llegada Prevista -->
                            <div>
                                <label for="planned_arrival_date" class="block text-sm font-medium text-gray-700">
                                    Llegada Prevista <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local" 
                                       name="planned_arrival_date" 
                                       id="planned_arrival_date" 
                                       value="{{ old('planned_arrival_date', $voyage->estimated_arrival_date ? $voyage->estimated_arrival_date->format('Y-m-d\TH:i') : '') }}"
                                       required
                                       @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') readonly @endif
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('planned_arrival_date') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                @error('planned_arrival_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Duración Estimada -->
                            <div>
                                <label for="estimated_duration_hours" class="block text-sm font-medium text-gray-700">
                                    Duración Estimada (horas)
                                </label>
                                <input type="number" 
                                       name="estimated_duration_hours" 
                                       id="estimated_duration_hours" 
                                       value="{{ old('estimated_duration_hours', $voyage->estimated_duration_hours) }}"
                                       min="1"
                                       step="0.5"
                                       @if(!$userPermissions['can_edit']) readonly @endif
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('estimated_duration_hours') border-red-300 @enderror @if(!$userPermissions['can_edit']) bg-gray-100 @endif"
                                       placeholder="Ej: 48">
                                @error('estimated_duration_hours')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Fechas reales (solo si están establecidas) -->
                        @if($voyage->actual_departure_date || $voyage->actual_arrival_date)
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h4 class="text-md font-medium text-gray-800 mb-3">Fechas Reales</h4>
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    @if($voyage->actual_departure_date)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Salida Real
                                            </label>
                                            <div class="mt-1 text-sm text-gray-900">
                                                {{ $voyage->actual_departure_date->format('d/m/Y H:i') }}
                                            </div>
                                        </div>
                                    @endif

                                    @if($voyage->actual_arrival_date)
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Llegada Real
                                            </label>
                                            <div class="mt-1 text-sm text-gray-900">
                                                {{ $voyage->actual_arrival_date->format('d/m/Y H:i') }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center justify-between">
                    <a href="{{ route('company.voyages.show', $voyage) }}" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancelar
                    </a>

                    @if($userPermissions['can_edit'])
                        <button type="submit" 
                                id="save-button"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Guardar Cambios
                        </button>
                    @endif
                </div>
            </form>

            <!-- Formulario oculto para eliminación -->
            @if(method_exists($voyage, 'canBeDeleted') && $voyage->canBeDeleted() && $userPermissions['can_delete'])
                <form id="delete-form" method="POST" action="{{ route('company.voyages.destroy', $voyage) }}" style="display: none;">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // Función para confirmar eliminación
            window.confirmDelete = function() {
                if (confirm('¿Está seguro de que desea eliminar este viaje?\n\nEsta acción no se puede deshacer y eliminará todos los shipments asociados.')) {
                    document.getElementById('delete-form').submit();
                }
            };

            // Filtrado de puertos por país
            const originCountrySelect = document.getElementById('origin_country_id');
            const originPortSelect = document.getElementById('origin_port_id');
            const destinationCountrySelect = document.getElementById('destination_country_id');
            const destinationPortSelect = document.getElementById('destination_port_id');

            function filterPortsByCountry(countrySelect, portSelect) {
                const selectedCountryId = countrySelect.value;
                const portOptions = portSelect.querySelectorAll('option');
                
                portOptions.forEach(option => {
                    if (option.value === '') {
                        option.style.display = 'block';
                        return;
                    }
                    
                    const portCountryId = option.getAttribute('data-country');
                    if (selectedCountryId === '' || portCountryId === selectedCountryId) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                        if (option.selected) {
                            option.selected = false;
                        }
                    }
                });

                // Resetear selección si el puerto actual no pertenece al país seleccionado
                const currentPort = portSelect.value;
                if (currentPort && selectedCountryId) {
                    const currentPortOption = portSelect.querySelector(`option[value="${currentPort}"]`);
                    if (currentPortOption && currentPortOption.getAttribute('data-country') !== selectedCountryId) {
                        portSelect.value = '';
                    }
                }
            }

            // Event listeners para filtrado de puertos
            originCountrySelect.addEventListener('change', function() {
                filterPortsByCountry(this, originPortSelect);
            });

            destinationCountrySelect.addEventListener('change', function() {
                filterPortsByCountry(this, destinationPortSelect);
            });

            // Aplicar filtrado inicial
            filterPortsByCountry(originCountrySelect, originPortSelect);
            filterPortsByCountry(destinationCountrySelect, destinationPortSelect);

            // Validación de fechas
            const plannedDepartureInput = document.getElementById('planned_departure_date');
            const plannedArrivalInput = document.getElementById('planned_arrival_date');
            const estimatedDurationInput = document.getElementById('estimated_duration_hours');

            function validateDates() {
                const departureDate = new Date(plannedDepartureInput.value);
                const arrivalDate = new Date(plannedArrivalInput.value);

                if (plannedDepartureInput.value && plannedArrivalInput.value) {
                    if (arrivalDate <= departureDate) {
                        plannedArrivalInput.setCustomValidity('La fecha de llegada debe ser posterior a la fecha de salida');
                        return false;
                    } else {
                        plannedArrivalInput.setCustomValidity('');
                        
                        // Calcular duración automáticamente si no está establecida
                        if (!estimatedDurationInput.value) {
                            const diffTime = Math.abs(arrivalDate - departureDate);
                            const diffHours = Math.ceil(diffTime / (1000 * 60 * 60));
                            estimatedDurationInput.value = diffHours;
                        }
                        return true;
                    }
                }
                
                plannedArrivalInput.setCustomValidity('');
                return true;
            }

            plannedDepartureInput.addEventListener('change', validateDates);
            plannedArrivalInput.addEventListener('change', validateDates);

            // Validación del formulario antes de enviar
            document.getElementById('voyage-form').addEventListener('submit', function(e) {
                if (!validateDates()) {
                    e.preventDefault();
                    alert('Por favor, corrija los errores en las fechas antes de continuar.');
                    return false;
                }

                // Validar que los puertos seleccionados pertenezcan a los países seleccionados
                const originCountryId = originCountrySelect.value;
                const originPortId = originPortSelect.value;
                const destinationCountryId = destinationCountrySelect.value;
                const destinationPortId = destinationPortSelect.value;

                if (originPortId && originCountryId) {
                    const originPortOption = originPortSelect.querySelector(`option[value="${originPortId}"]`);
                    if (originPortOption && originPortOption.getAttribute('data-country') !== originCountryId) {
                        e.preventDefault();
                        alert('El puerto de origen seleccionado no pertenece al país de origen.');
                        return false;
                    }
                }

                if (destinationPortId && destinationCountryId) {
                    const destinationPortOption = destinationPortSelect.querySelector(`option[value="${destinationPortId}"]`);
                    if (destinationPortOption && destinationPortOption.getAttribute('data-country') !== destinationCountryId) {
                        e.preventDefault();
                        alert('El puerto de destino seleccionado no pertenece al país de destino.');
                        return false;
                    }
                }

                // Validar que origen y destino sean diferentes
                if (originPortId && destinationPortId && originPortId === destinationPortId) {
                    e.preventDefault();
                    alert('El puerto de origen y destino no pueden ser el mismo.');
                    return false;
                }

                // Confirmación para cambios críticos
                const currentStatus = '{{ $voyage->status }}';
                const newStatus = document.getElementById('status').value;
                
                if (currentStatus !== newStatus && (newStatus === 'cancelled' || newStatus === 'completed')) {
                    const statusLabels = {
                        'cancelled': 'cancelar',
                        'completed': 'completar'
                    };
                    
                    const action = statusLabels[newStatus];
                    if (!confirm(`¿Está seguro de que desea ${action} este viaje?\n\nEsta acción afectará todos los shipments asociados.`)) {
                        e.preventDefault();
                        return false;
                    }
                }

                // Deshabilitar botón de envío para evitar doble envío
                const saveButton = document.getElementById('save-button');
                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Guardando...';
                }
            });

            // Restablecer estados de error al escribir
            document.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('input', function() {
                    this.classList.remove('border-red-300');
                });
            });

            // Mostrar información adicional de la embarcación seleccionada
            const vesselSelect = document.getElementById('vessel_id');
            vesselSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const capacity = selectedOption.getAttribute('data-capacity');
                    const imo = selectedOption.getAttribute('data-imo');
                    
                    // Aquí podrías mostrar información adicional si fuera necesario
                    console.log(`Embarcación seleccionada: IMO ${imo}, Capacidad: ${capacity} tons`);
                }
            });
        });
    </script>
    @endpush
</x-app-layout>