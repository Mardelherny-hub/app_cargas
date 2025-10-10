{{-- resources/views/company/shipments/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Editar Carga (Shipment)') }} #{{ $shipment->shipment_number }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('company.shipments.show', $shipment) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition-colors">
                    Ver Detalle
                </a>
                <a href="{{ route('company.shipments.index') }}" 
                   class="bg-gray-600 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded transition-colors">
                    Volver a Lista
                </a>
            </div>
        </div>
    </x-slot>

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

    {{-- AGREGAR ESTA SECCIÓN INMEDIATAMENTE DESPUÉS DEL x-slot "header" Y ANTES DEL @php --}}

{{-- Mensajes Flash de Éxito/Error/Información --}}
@if(session('success'))
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('success') }}</span>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg onclick="this.parentElement.parentElement.style.display='none'" 
                 class="fill-current h-6 w-6 text-green-500 cursor-pointer" 
                 role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <title>Cerrar</title>
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </span>
    </div>
@endif

@if(session('error'))
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('error') }}</span>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg onclick="this.parentElement.parentElement.style.display='none'" 
                 class="fill-current h-6 w-6 text-red-500 cursor-pointer" 
                 role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <title>Cerrar</title>
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </span>
    </div>
@endif

@if(session('warning'))
    <div class="mb-6 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('warning') }}</span>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg onclick="this.parentElement.parentElement.style.display='none'" 
                 class="fill-current h-6 w-6 text-yellow-500 cursor-pointer" 
                 role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <title>Cerrar</title>
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </span>
    </div>
@endif

@if(session('info'))
    <div class="mb-6 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ session('info') }}</span>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg onclick="this.parentElement.parentElement.style.display='none'" 
                 class="fill-current h-6 w-6 text-blue-500 cursor-pointer" 
                 role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <title>Cerrar</title>
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </span>
    </div>
@endif

{{-- Errores de Validación --}}
@if($errors->any())
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">¡Errores de validación!</strong>
        <ul class="mt-2 list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
            <svg onclick="this.parentElement.style.display='none'" 
                 class="fill-current h-6 w-6 text-red-500 cursor-pointer" 
                 role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <title>Cerrar</title>
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </span>
    </div>
@endif

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(in_array('Cargas', $companyRoles))
                <form method="POST" action="{{ route('company.shipments.update', $shipment) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    {{-- Información del Viaje --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Información del Viaje
                            </h3>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <!-- Viaje (Solo lectura en edición) -->
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Viaje</label>
                                    <div class="mt-1 flex rounded-md shadow-sm">
                                        <span class="inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                            Fijo
                                        </span>
                                        <input type="text" 
                                               readonly
                                               value="{{ $shipment->voyage->voyage_number }} ({{ $shipment->voyage->originPort->name ?? 'N/A' }} → {{ $shipment->voyage->destinationPort->name ?? 'N/A' }})"
                                               class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">El viaje no puede cambiarse al editar un shipment existente.</p>
                                </div>

                                <!-- Número de Shipment -->
                                <div>
                                    <label for="shipment_number" class="block text-sm font-medium text-gray-700">
                                        Número de Carga <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="shipment_number" 
                                           id="shipment_number" 
                                           value="{{ old('shipment_number', $shipment->shipment_number) }}"
                                           required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('shipment_number') border-red-300 @enderror">
                                    @error('shipment_number')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Secuencia en Viaje (Solo lectura) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Secuencia en Viaje</label>
                                    <input type="text" 
                                           readonly
                                           value="{{ $shipment->sequence_in_voyage }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-gray-500 shadow-sm sm:text-sm">
                                    <p class="mt-1 text-sm text-gray-500">La secuencia se mantiene fija.</p>
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
                                <!-- Embarcación (Solo lectura en edición) -->
                                {{-- REEMPLAZAR EL CAMPO DE EMBARCACIÓN EN edit.blade.php --}}
{{-- Ubicación: Sección "Embarcación y Capitán" --}}

<!-- Embarcación (Condicional: editable si requires_attention) -->
<div class="sm:col-span-2">
    <label for="vessel_id" class="block text-sm font-medium text-gray-700">
        Embarcación 
        @if($shipment->requires_attention)
            <span class="text-red-500">*</span>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-2">
                Requiere Cambio
            </span>
        @endif
    </label>
    
    @if($shipment->requires_attention)
        {{-- MODO EDITABLE: Para shipments duplicados que requieren atención --}}
        <select name="vessel_id" 
                id="vessel_id" 
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('vessel_id') border-red-300 @enderror">
            <option value="">Seleccione una embarcación</option>
            @foreach($formData['vessels'] ?? [] as $vessel)
                <option value="{{ $vessel['id'] }}" {{ old('vessel_id', $shipment->vessel_id) == $vessel['id'] ? 'selected' : '' }}>
                    {{ $vessel['display_name'] }}
                </option>
            @endforeach
        </select>
        @error('vessel_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-sm text-red-600">
            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            Este shipment fue duplicado y requiere seleccionar la embarcación correcta.
        </p>
    @else
        {{-- MODO SOLO LECTURA: Para shipments normales --}}
        <div class="mt-1 flex rounded-md shadow-sm">
            <span class="inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-gray-50 text-gray-500 text-sm">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Fijo
            </span>
            <input type="text" 
                   readonly
                   value="{{ $shipment->vessel->name ?? 'Sin asignar' }} ({{ $shipment->vessel->registration_number ?? 'N/A' }})"
                   class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
        </div>
        <p class="mt-1 text-sm text-gray-500">La embarcación no puede cambiarse en shipments normales.</p>
    @endif
</div>

                                <!-- Capitán (Editable) -->
                                <div class="sm:col-span-2">
                                    <label for="captain_id" class="block text-sm font-medium text-gray-700">
                                        Capitán
                                    </label>
                                    <select name="captain_id" 
                                            id="captain_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('captain_id') border-red-300 @enderror">
                                        <option value="">Seleccione un capitán (opcional)</option>
                                        @foreach($captains as $captain)
                                            <option value="{{ $captain['id'] }}" {{ old('captain_id', $shipment->captain_id) == $captain['id'] ? 'selected' : '' }}>
                                                {{ $captain['display_name'] }}
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

                    {{-- Configuración del Convoy --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Configuración del Convoy
                            </h3>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                                <!-- Rol en Convoy -->
                                <div>
                                    <label for="vessel_role" class="block text-sm font-medium text-gray-700">
                                        Rol en Convoy <span class="text-red-500">*</span>
                                    </label>
                                    <select name="vessel_role" 
                                            id="vessel_role" 
                                            required
                                            onchange="updateConvoyFields()"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('vessel_role') border-red-300 @enderror">
                                        @foreach($vesselRoles as $value => $label)
                                            <option value="{{ $value }}" {{ old('vessel_role', $shipment->vessel_role) === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vessel_role')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Posición en Convoy -->
                                <div id="convoy_position_group" style="display: {{ old('vessel_role', $shipment->vessel_role) !== 'single' ? 'block' : 'none' }};">
                                    <label for="convoy_position" class="block text-sm font-medium text-gray-700">
                                        Posición en Convoy
                                    </label>
                                    <input type="number" 
                                           name="convoy_position" 
                                           id="convoy_position" 
                                           value="{{ old('convoy_position', $shipment->convoy_position) }}"
                                           min="1"
                                           max="20"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('convoy_position') border-red-300 @enderror">
                                    @error('convoy_position')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-sm text-gray-500">Orden en el convoy (1, 2, 3...)</p>
                                </div>

                                <!-- Es Embarcación Líder -->
                                <div id="is_lead_vessel_group" style="display: {{ old('vessel_role', $shipment->vessel_role) !== 'single' ? 'block' : 'none' }};">
                                    <label for="is_lead_vessel" class="block text-sm font-medium text-gray-700">
                                        Embarcación Líder
                                    </label>
                                    <div class="mt-1">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" 
                                                   name="is_lead_vessel" 
                                                   id="is_lead_vessel" 
                                                   value="1"
                                                   {{ old('is_lead_vessel', $shipment->is_lead_vessel) ? 'checked' : '' }}
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <span class="ml-2 text-sm text-gray-700">Es la embarcación líder del convoy</span>
                                        </label>
                                    </div>
                                    @error('is_lead_vessel')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
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

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                <!-- Capacidad de Carga en Toneladas -->
                                <div>
                                    <label for="cargo_capacity_tons" class="block text-sm font-medium text-gray-700">
                                        Capacidad Carga (Toneladas) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" 
                                           name="cargo_capacity_tons" 
                                           id="cargo_capacity_tons" 
                                           value="{{ old('cargo_capacity_tons', $shipment->cargo_capacity_tons) }}"
                                           step="0.01"
                                           min="0"
                                           max="99999.99"
                                           required
                                           onchange="updateCapacityInfo()"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('cargo_capacity_tons') border-red-300 @enderror">
                                    @error('cargo_capacity_tons')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Capacidad de Contenedores -->
                                <div>
                                    <label for="container_capacity" class="block text-sm font-medium text-gray-700">
                                        Capacidad Contenedores
                                    </label>
                                    <input type="number" 
                                           name="container_capacity" 
                                           id="container_capacity" 
                                           value="{{ old('container_capacity', $shipment->container_capacity) }}"
                                           min="0"
                                           max="9999"
                                           onchange="updateCapacityInfo()"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('container_capacity') border-red-300 @enderror">
                                    @error('container_capacity')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Información de capacidad desde la embarcación -->
                                <div id="vessel_capacity_info" class="col-span-1">
                                    <label class="block text-sm font-medium text-gray-700">Capacidad de la Embarcación</label>
                                    <div class="mt-1 p-3 bg-gray-50 rounded-md">
                                        <p class="text-sm text-gray-700">
                                            <span class="font-medium">{{ $shipment->vessel->name ?? 'Sin embarcación' }}</span><br>
                                            Carga: {{ number_format($shipment->vessel->cargo_capacity_tons ?? 0, 2) }} ton<br>
                                            Contenedores: {{ $shipment->vessel->container_capacity ?? 0 }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Estado --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Estado
                            </h3>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <!-- Estado -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700">
                                        Estado <span class="text-red-500">*</span>
                                    </label>
                                    <select name="status" 
                                            id="status" 
                                            required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('status') border-red-300 @enderror">
                                        @foreach($statusOptions as $value => $label)
                                            <option value="{{ $value }}" {{ old('status', $shipment->status) === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('status')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Estado Actual -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Estado Actual</label>
                                    <div class="mt-1 p-3 bg-gray-50 rounded-md">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @switch($shipment->status)
                                                @case('planning') bg-yellow-100 text-yellow-800 @break
                                                @case('loading') bg-blue-100 text-blue-800 @break
                                                @case('loaded') bg-green-100 text-green-800 @break
                                                @case('in_transit') bg-purple-100 text-purple-800 @break
                                                @case('arrived') bg-indigo-100 text-indigo-800 @break
                                                @case('discharging') bg-orange-100 text-orange-800 @break
                                                @case('completed') bg-green-100 text-green-800 @break
                                                @case('delayed') bg-red-100 text-red-800 @break
                                                @default bg-gray-100 text-gray-800
                                            @endswitch">
                                            {{ ucfirst($shipment->status) }}
                                        </span>
                                    </div>
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
                                       value="{{ old('origin_manifest_id', $shipment->origin_manifest_id) }}"
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
                                       value="{{ old('origin_transport_doc', $shipment->origin_transport_doc) }}"
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
                        <a href="{{ route('company.shipments.show', $shipment) }}" 
                           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition-colors">
                            Cancelar
                        </a>
                        
                        @if(in_array($shipment->status, ['planning', 'loading']))
                            <button type="button" 
                                    onclick="duplicateShipment()"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors">
                                Duplicar
                            </button>
                        @endif

                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-800 text-white font-bold py-2 px-4 rounded transition-colors">
                            Guardar Cambios
                        </button>
                    </div>
                </form>

            @else
                {{-- Sin permisos --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Sin permisos para Cargas</h3>
                            <p class="mt-2 text-sm text-yellow-700">
                                Su empresa no tiene el rol "Cargas" asignado. Contacte al administrador para solicitar acceso a la gestión de cargas.
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Scripts JavaScript --}}
    <script>
        /**
         * Actualizar campos de convoy según el rol seleccionado
         */
        function updateConvoyFields() {
            const vesselRole = document.getElementById('vessel_role').value;
            const convoyPositionGroup = document.getElementById('convoy_position_group');
            const isLeadVesselGroup = document.getElementById('is_lead_vessel_group');
            const convoyPosition = document.getElementById('convoy_position');
            const isLeadVessel = document.getElementById('is_lead_vessel');
            
            if (vesselRole === 'single') {
                convoyPositionGroup.style.display = 'none';
                isLeadVesselGroup.style.display = 'none';
                convoyPosition.value = '';
                isLeadVessel.checked = false;
            } else {
                convoyPositionGroup.style.display = 'block';
                isLeadVesselGroup.style.display = 'block';
                
                // Auto-asignar valores por defecto
                if (!convoyPosition.value) {
                    convoyPosition.value = vesselRole === 'lead' ? '1' : '2';
                }
                if (vesselRole === 'lead') {
                    isLeadVessel.checked = true;
                }
            }
        }

        /**
         * Actualizar información de capacidad
         */
        function updateCapacityInfo() {
            // Esta función se puede expandir para mostrar cálculos dinámicos
            console.log('Capacity updated');
        }

        /**
         * Duplicar shipment
         */
        function duplicateShipment() {
            if (confirm('¿Desea crear una copia de este shipment? Se abrirá el formulario de creación con los datos pre-cargados.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("company.shipments.duplicate", $shipment) }}';
                
                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = '{{ csrf_token() }}';
                form.appendChild(token);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Inicializar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            updateConvoyFields();
        });
    </script>
</x-app-layout>