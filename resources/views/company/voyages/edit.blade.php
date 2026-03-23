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
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        Editar Viaje {{ $voyage->voyage_number }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        @if(in_array('Cargas', $companyRoles))
                            Modificar información del viaje
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

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Mensajes flash --}}
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif
            @if(session('warning'))
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 text-sm text-yellow-700">
                    {{ session('warning') }}
                </div>
            @endif

            {{-- Errores de validación --}}
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-red-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-red-800">Por favor corrija los siguientes errores:</p>
                            <ul class="mt-1 list-disc list-inside text-sm text-red-700 space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('company.voyages.update', $voyage) }}" id="voyage-form">
                @csrf
                @method('PUT')

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- SECCIÓN 1 — DATOS OBLIGATORIOS WS                  --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="bg-white shadow rounded-lg border-l-4 border-blue-500">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Datos del Viaje</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Campos requeridos por AFIP y DNA Paraguay</p>
                    </div>
                    <div class="px-6 py-5 space-y-5">

                        {{-- Número de viaje + Estado --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="voyage_number" class="block text-sm font-medium text-gray-700 mb-1">
                                    Número de Viaje <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="voyage_number"
                                       id="voyage_number"
                                       value="{{ old('voyage_number', $voyage->voyage_number) }}"
                                       required
                                       maxlength="50"
                                       placeholder="Ej: V001-2025"
                                       @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') readonly @endif
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('voyage_number') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                @error('voyage_number')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                    Estado <span class="text-red-500">*</span>
                                </label>
                                <select name="status"
                                        id="status"
                                        required
                                        @if(!$userPermissions['can_edit']) disabled @endif
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('status') border-red-300 @enderror @if(!$userPermissions['can_edit']) bg-gray-100 @endif">
                                    @foreach([
                                        'draft'       => 'Borrador',
                                        'planning'    => 'En Planificación',
                                        'loading'     => 'Cargando',
                                        'in_progress' => 'En Tránsito',
                                        'arrived'     => 'Arribado',
                                        'completed'   => 'Completado',
                                        'cancelled'   => 'Cancelado',
                                    ] as $val => $label)
                                        <option value="{{ $val }}" {{ old('status', $voyage->status) === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Embarcación + Capitán --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="lead_vessel_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Embarcación <span class="text-red-500">*</span>
                                </label>
                                <select name="lead_vessel_id"
                                        id="lead_vessel_id"
                                        required
                                        @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('lead_vessel_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                    <option value="">Seleccione embarcación</option>
                                    @foreach($formData['vessels'] as $vessel)
                                        <option value="{{ $vessel->id }}"
                                                data-capacity="{{ $vessel->cargo_capacity_tons }}"
                                                data-imo="{{ $vessel->imo_number }}"
                                                {{ old('lead_vessel_id', $voyage->lead_vessel_id) == $vessel->id ? 'selected' : '' }}>
                                            {{ $vessel->name }} — {{ $vessel->imo_number }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('lead_vessel_id')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="captain_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Capitán
                                </label>
                                <select name="captain_id"
                                        id="captain_id"
                                        @if(!$userPermissions['can_edit']) disabled @endif
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('captain_id') border-red-300 @enderror @if(!$userPermissions['can_edit']) bg-gray-100 @endif">
                                    <option value="">Sin capitán asignado</option>
                                    @foreach($formData['captains'] as $captain)
                                        <option value="{{ $captain->id }}" {{ old('captain_id', $voyage->captain_id) == $captain->id ? 'selected' : '' }}>
                                            {{ $captain->full_name }} — Lic: {{ $captain->license_number }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('captain_id')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Ruta: Origen --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Origen</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="origin_country_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        País de Origen <span class="text-red-500">*</span>
                                    </label>
                                    <select name="origin_country_id"
                                            id="origin_country_id"
                                            required
                                            @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('origin_country_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                        <option value="">Seleccione país</option>
                                        @foreach($formData['countries'] as $country)
                                            <option value="{{ $country->id }}" {{ old('origin_country_id', $voyage->origin_country_id) == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('origin_country_id')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="origin_port_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        Puerto de Origen <span class="text-red-500">*</span>
                                    </label>
                                    <select name="origin_port_id"
                                            id="origin_port_id"
                                            required
                                            @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('origin_port_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
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
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Ruta: Destino --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Destino</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="destination_country_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        País de Destino <span class="text-red-500">*</span>
                                    </label>
                                    <select name="destination_country_id"
                                            id="destination_country_id"
                                            required
                                            @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('destination_country_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                        <option value="">Seleccione país</option>
                                        @foreach($formData['countries'] as $country)
                                            <option value="{{ $country->id }}" {{ old('destination_country_id', $voyage->destination_country_id) == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('destination_country_id')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="destination_port_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        Puerto de Destino <span class="text-red-500">*</span>
                                    </label>
                                    <select name="destination_port_id"
                                            id="destination_port_id"
                                            required
                                            @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') disabled @endif
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('destination_port_id') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
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
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Fechas --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="departure_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha y Hora de Salida
                                    @if(in_array($voyage->status, ['loading','in_progress','arrived','completed']))
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>
                                <input type="datetime-local"
                                       name="departure_date"
                                       id="departure_date"
                                       value="{{ old('departure_date', $voyage->departure_date ? $voyage->departure_date->format('Y-m-d\TH:i') : '') }}"
                                       @if(in_array($voyage->status, ['loading','in_progress','arrived','completed'])) required @endif
                                       @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') readonly @endif
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('departure_date') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                @error('departure_date')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                                @if(in_array($voyage->status, ['draft','planning','cancelled']) && $userPermissions['can_edit'])
                                    <button type="button"
                                            onclick="document.getElementById('departure_date').value = ''"
                                            class="mt-1 text-xs text-blue-600 hover:text-blue-800 underline">
                                        Limpiar fecha
                                    </button>
                                @endif
                            </div>

                            <div>
                                <label for="estimated_arrival_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha y Hora Estimada de Llegada
                                    @if(in_array($voyage->status, ['loading','in_progress','arrived','completed']))
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>
                                <input type="datetime-local"
                                       name="estimated_arrival_date"
                                       id="estimated_arrival_date"
                                       value="{{ old('estimated_arrival_date', $voyage->estimated_arrival_date ? $voyage->estimated_arrival_date->format('Y-m-d\TH:i') : '') }}"
                                       @if(in_array($voyage->status, ['loading','in_progress','arrived','completed'])) required @endif
                                       @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') readonly @endif
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('estimated_arrival_date') border-red-300 @enderror @if(!$userPermissions['can_edit'] || $voyage->status === 'completed') bg-gray-100 @endif">
                                @error('estimated_arrival_date')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                                @if(in_array($voyage->status, ['draft','planning','cancelled']) && $userPermissions['can_edit'])
                                    <button type="button"
                                            onclick="document.getElementById('estimated_arrival_date').value = ''"
                                            class="mt-1 text-xs text-blue-600 hover:text-blue-800 underline">
                                        Limpiar fecha
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Fechas reales (solo lectura, si existen) --}}
                        @if($voyage->actual_departure_date || $voyage->actual_arrival_date)
                            <div class="pt-4 border-t border-gray-100">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Fechas Reales</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @if($voyage->actual_departure_date)
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Salida Real</p>
                                            <p class="text-sm text-gray-900 mt-1">{{ $voyage->actual_departure_date->format('d/m/Y H:i') }}</p>
                                        </div>
                                    @endif
                                    @if($voyage->actual_arrival_date)
                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Llegada Real</p>
                                            <p class="text-sm text-gray-900 mt-1">{{ $voyage->actual_arrival_date->format('d/m/Y H:i') }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Estado de carga (selector único → is_empty_transport + has_cargo_onboard) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Estado de Carga <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label id="label-con-carga"
                                       class="flex items-start gap-3 p-3 border-2 rounded-lg transition-all {{ $userPermissions['can_edit'] ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed' }}">
                                    <input type="radio" name="cargo_status" value="con_carga"
                                           {{ in_array(old('is_empty_transport', $voyage->is_empty_transport), ['N', null, '']) ? 'checked' : '' }}
                                           @if(!$userPermissions['can_edit']) disabled @endif
                                           class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Con carga</p>
                                        <p class="text-xs text-gray-500">Lleva mercadería o contenedores (incluye contenedores vacíos)</p>
                                    </div>
                                </label>
                                <label id="label-lastre"
                                       class="flex items-start gap-3 p-3 border-2 rounded-lg transition-all {{ $userPermissions['can_edit'] ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed' }}">
                                    <input type="radio" name="cargo_status" value="lastre"
                                           {{ old('is_empty_transport', $voyage->is_empty_transport) === 'S' ? 'checked' : '' }}
                                           @if(!$userPermissions['can_edit']) disabled @endif
                                           class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">En lastre</p>
                                        <p class="text-xs text-gray-500">Sin ninguna carga ni contenedores a bordo</p>
                                    </div>
                                </label>
                            </div>
                            {{-- Alerta lastre --}}
                            <div id="lastre-warning"
                                 class="{{ old('is_empty_transport', $voyage->is_empty_transport) === 'S' ? '' : 'hidden' }} mt-3 p-3 bg-amber-50 border border-amber-200 rounded-md">
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <p class="text-xs text-amber-700">
                                        <strong>Viaje en lastre:</strong> No se enviarán BLs ni contenedores a AFIP/DNA. El flujo será solo XFFM → XFCT.
                                    </p>
                                </div>
                            </div>
                            {{-- Campos ocultos reales que lee el controlador --}}
                            <input type="hidden" name="is_empty_transport" id="is_empty_transport"
                                   value="{{ old('is_empty_transport', $voyage->is_empty_transport ?? 'N') }}">
                            <input type="hidden" name="has_cargo_onboard" id="has_cargo_onboard"
                                   value="{{ old('has_cargo_onboard', $voyage->has_cargo_onboard ?? 'S') }}">
                        </div>

                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- SECCIÓN 2 — INFORMACIÓN ADICIONAL (colapsable)     --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="bg-white shadow rounded-lg"
                     x-data="{ open: {{ $errors->hasAny(['internal_reference','estimated_duration_hours','notes']) ? 'true' : 'false' }} }">
                    <button type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 rounded-lg transition-colors">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Información Adicional</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Referencia interna, duración estimada y observaciones</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" class="border-t border-gray-100">
                        <div class="px-6 py-5 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="internal_reference" class="block text-sm font-medium text-gray-700 mb-1">
                                        Referencia Interna
                                    </label>
                                    <input type="text"
                                           name="internal_reference"
                                           id="internal_reference"
                                           value="{{ old('internal_reference', $voyage->internal_reference) }}"
                                           maxlength="100"
                                           placeholder="Referencia para uso interno"
                                           @if(!$userPermissions['can_edit']) readonly @endif
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @if(!$userPermissions['can_edit']) bg-gray-100 @endif">
                                </div>
                                <div>
                                    <label for="estimated_duration_hours" class="block text-sm font-medium text-gray-700 mb-1">
                                        Duración Estimada (horas)
                                    </label>
                                    <input type="number"
                                           name="estimated_duration_hours"
                                           id="estimated_duration_hours"
                                           value="{{ old('estimated_duration_hours', $voyage->estimated_duration_hours) }}"
                                           min="1"
                                           step="0.5"
                                           placeholder="Ej: 48"
                                           @if(!$userPermissions['can_edit']) readonly @endif
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('estimated_duration_hours') border-red-300 @enderror @if(!$userPermissions['can_edit']) bg-gray-100 @endif">
                                    @error('estimated_duration_hours')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                                    Observaciones
                                </label>
                                <textarea name="notes"
                                          id="notes"
                                          rows="3"
                                          placeholder="Información adicional sobre el viaje"
                                          @if(!$userPermissions['can_edit']) readonly @endif
                                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @if(!$userPermissions['can_edit']) bg-gray-100 @endif">{{ old('notes', $voyage->voyage_notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- BOTONES                                             --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('company.voyages.show', $voyage) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancelar
                    </a>
                    @if($userPermissions['can_edit'])
                        <button type="submit"
                                id="submit-btn"
                                class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Guardar Cambios
                        </button>
                    @endif
                </div>

            </form>

            {{-- Formulario oculto para eliminación --}}
            @if(method_exists($voyage, 'canBeDeleted') && $voyage->canBeDeleted() && $userPermissions['can_delete'])
                <form id="delete-form" method="POST" action="{{ route('company.voyages.destroy', $voyage) }}" style="display:none;">
                    @csrf
                    @method('DELETE')
                </form>
            @endif

        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── Filtrado de puertos por país ──────────────────────────────
        const originCountry      = document.getElementById('origin_country_id');
        const originPort         = document.getElementById('origin_port_id');
        const destinationCountry = document.getElementById('destination_country_id');
        const destinationPort    = document.getElementById('destination_port_id');

        function filterPortsByCountry(countrySelect, portSelect) {
            const selectedId = countrySelect.value;
            portSelect.querySelectorAll('option').forEach(function (opt) {
                if (!opt.value) { opt.style.display = 'block'; return; }
                const matches = !selectedId || opt.getAttribute('data-country') === selectedId;
                opt.style.display = matches ? 'block' : 'none';
                if (!matches && opt.selected) {
                    opt.selected = false;
                    portSelect.value = '';
                }
            });
        }

        if (originCountry && originPort) {
            originCountry.addEventListener('change', function () {
                filterPortsByCountry(this, originPort);
            });
            filterPortsByCountry(originCountry, originPort);
        }

        if (destinationCountry && destinationPort) {
            destinationCountry.addEventListener('change', function () {
                filterPortsByCountry(this, destinationPort);
            });
            filterPortsByCountry(destinationCountry, destinationPort);
        }

        // ── Validación de fechas ──────────────────────────────────────
        const departureInput = document.getElementById('departure_date');
        const arrivalInput   = document.getElementById('estimated_arrival_date');
        const durationInput  = document.getElementById('estimated_duration_hours');

        function validateDates() {
            if (departureInput.value && arrivalInput.value) {
                const invalid = new Date(arrivalInput.value) <= new Date(departureInput.value);
                arrivalInput.setCustomValidity(
                    invalid ? 'La fecha de llegada debe ser posterior a la fecha de salida' : ''
                );
                return !invalid;
            }
            arrivalInput.setCustomValidity('');
            return true;
        }

        if (departureInput && arrivalInput) {
            departureInput.addEventListener('change', function () {
                validateDates();
                // Calcular duración automáticamente si ambas fechas están completas
                if (this.value && arrivalInput.value && durationInput && !durationInput.value) {
                    const diff = Math.ceil(
                        (new Date(arrivalInput.value) - new Date(this.value)) / (1000 * 60 * 60)
                    );
                    if (diff > 0) durationInput.value = diff;
                }
            });
            arrivalInput.addEventListener('change', validateDates);
        }

        // ── Estado de carga (radio → hidden fields) ───────────────────
        const radios        = document.querySelectorAll('input[name="cargo_status"]');
        const hiddenEmpty   = document.getElementById('is_empty_transport');
        const hiddenCargo   = document.getElementById('has_cargo_onboard');
        const lastreWarning = document.getElementById('lastre-warning');
        const labelConCarga = document.getElementById('label-con-carga');
        const labelLastre   = document.getElementById('label-lastre');

        function updateCargoStatus() {
            const isLastre = document.querySelector('input[name="cargo_status"]:checked')?.value === 'lastre';

            if (hiddenEmpty) hiddenEmpty.value = isLastre ? 'S' : 'N';
            if (hiddenCargo) hiddenCargo.value = isLastre ? 'N' : 'S';
            if (lastreWarning) lastreWarning.classList.toggle('hidden', !isLastre);

            if (labelConCarga) {
                labelConCarga.classList.toggle('border-blue-500', !isLastre);
                labelConCarga.classList.toggle('bg-blue-50',      !isLastre);
                labelConCarga.classList.toggle('border-gray-200',  isLastre);
                labelConCarga.classList.toggle('bg-white',         isLastre);
            }
            if (labelLastre) {
                labelLastre.classList.toggle('border-blue-500',  isLastre);
                labelLastre.classList.toggle('bg-blue-50',       isLastre);
                labelLastre.classList.toggle('border-gray-200', !isLastre);
                labelLastre.classList.toggle('bg-white',        !isLastre);
            }
        }

        radios.forEach(r => r.addEventListener('change', updateCargoStatus));
        updateCargoStatus(); // Estado inicial desde valores de BD

        // ── Validación al enviar ──────────────────────────────────────
        const form = document.getElementById('voyage-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!validateDates()) {
                    e.preventDefault();
                    alert('La fecha de llegada debe ser posterior a la fecha de salida.');
                    return;
                }

                if (originPort && destinationPort &&
                    originPort.value && destinationPort.value &&
                    originPort.value === destinationPort.value) {
                    e.preventDefault();
                    alert('El puerto de origen y destino no pueden ser el mismo.');
                    return;
                }

                // Confirmar cambios a estados críticos
                const currentStatus = '{{ $voyage->status }}';
                const newStatus     = document.getElementById('status')?.value;
                if (currentStatus !== newStatus &&
                    (newStatus === 'cancelled' || newStatus === 'completed')) {
                    const labels = { cancelled: 'cancelar', completed: 'completar' };
                    if (!confirm(`¿Está seguro de que desea ${labels[newStatus]} este viaje?\n\nEsta acción afectará todos los shipments asociados.`)) {
                        e.preventDefault();
                        return;
                    }
                }

                // Spinner en botón
                const btn = document.getElementById('submit-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Guardando...';
                }
            });
        }

        // ── Confirmar eliminación ─────────────────────────────────────
        window.confirmDelete = function () {
            if (confirm('¿Está seguro de que desea eliminar este viaje?\n\nEsta acción no se puede deshacer y eliminará todos los shipments asociados.')) {
                document.getElementById('delete-form').submit();
            }
        };

    });
    </script>
    @endpush

</x-app-layout>