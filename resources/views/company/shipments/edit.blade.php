{{-- resources/views/company/shipments/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Carga #{{ $shipment->shipment_number }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Viaje {{ $shipment->voyage->voyage_number }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('company.shipments.show', $shipment) }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Ver Detalle
                </a>
                <a href="{{ route('company.shipments.index') }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Lista de Cargas
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

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Mensajes flash --}}
            @foreach(['success' => 'green', 'error' => 'red', 'warning' => 'yellow', 'info' => 'blue'] as $type => $color)
                @if(session($type))
                    <div class="bg-{{ $color }}-50 border border-{{ $color }}-200 rounded-lg px-4 py-3 text-sm text-{{ $color }}-700">
                        {{ session($type) }}
                    </div>
                @endif
            @endforeach

            {{-- Errores de validación --}}
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-red-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-red-800">Por favor corrija los siguientes errores:</p>
                            <ul class="mt-1 list-disc list-inside text-sm text-red-700 space-y-0.5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            @if(in_array('Cargas', $companyRoles))

                <form method="POST" action="{{ route('company.shipments.update', $shipment) }}" id="shipment-form">
                    @csrf
                    @method('PUT')

                    {{-- ═══════════════════════════════════════════ --}}
                    {{-- SECCIÓN 1 — DATOS PRINCIPALES              --}}
                    {{-- ═══════════════════════════════════════════ --}}
                    <div class="bg-white shadow rounded-lg border-l-4 border-blue-500">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h3 class="text-base font-semibold text-gray-900">Datos de la Carga</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Información principal del shipment</p>
                        </div>
                        <div class="px-6 py-5 space-y-5">

                            {{-- Viaje (solo lectura) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Viaje</label>
                                <div class="flex rounded-md shadow-sm">
                                    <span class="inline-flex items-center px-3 py-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                    </span>
                                    <input type="text" readonly
                                           value="{{ $shipment->voyage->voyage_number }} ({{ $shipment->voyage->originPort->name ?? 'N/A' }} → {{ $shipment->voyage->destinationPort->name ?? 'N/A' }})"
                                           class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                </div>
                                <p class="mt-1 text-xs text-gray-400">El viaje no puede cambiarse al editar.</p>
                            </div>

                            {{-- Número + Secuencia --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="shipment_number" class="block text-sm font-medium text-gray-700 mb-1">
                                        Número de Carga <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           name="shipment_number"
                                           id="shipment_number"
                                           value="{{ old('shipment_number', $shipment->shipment_number) }}"
                                           required
                                           maxlength="50"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('shipment_number') border-red-300 @enderror">
                                    @error('shipment_number')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Secuencia en Viaje</label>
                                    <input type="text" readonly
                                           value="{{ $shipment->sequence_in_voyage }}"
                                           class="block w-full rounded-md border-gray-200 bg-gray-50 text-gray-400 sm:text-sm cursor-not-allowed">
                                    <p class="mt-1 text-xs text-gray-400">La secuencia se mantiene fija.</p>
                                </div>
                            </div>

                            {{-- Embarcación y Capitán --}}
                            @if($shipment->requires_attention)
                                {{-- Shipment duplicado: permite cambiar la embarcación --}}
                                <div>
                                    <label for="vessel_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        Embarcación <span class="text-red-500">*</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-2">
                                            Requiere Cambio
                                        </span>
                                    </label>
                                    <select name="vessel_id"
                                            id="vessel_id"
                                            required
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('vessel_id') border-red-300 @enderror">
                                        <option value="">Seleccione una embarcación</option>
                                        @foreach($formData['vessels'] ?? [] as $vessel)
                                            <option value="{{ $vessel['id'] }}"
                                                    {{ old('vessel_id', $shipment->vessel_id) == $vessel['id'] ? 'selected' : '' }}>
                                                {{ $vessel['display_name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vessel_id')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-red-600">
                                        ⚠ Este shipment fue duplicado y requiere seleccionar la embarcación correcta.
                                    </p>
                                </div>
                            @else
                                {{-- Shipment normal: embarcación y capitán heredados, solo lectura --}}
                                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-xs font-medium text-blue-700 mb-2">Embarcación y Capitán (del viaje)</p>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-xs text-blue-600">Embarcación</p>
                                            <p class="text-sm text-blue-900 font-medium">{{ $shipment->vessel->name ?? 'No asignada' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-blue-600">Capitán</p>
                                            <p class="text-sm text-blue-900 font-medium">{{ $shipment->voyage->captain->full_name ?? 'No asignado' }}</p>
                                        </div>
                                    </div>
                                </div>
                                {{-- Ocultos para que el update() los reciba --}}
                                <input type="hidden" name="vessel_id"  value="{{ $shipment->vessel_id }}">
                            @endif
                            <input type="hidden" name="captain_id" value="{{ $shipment->voyage->captain_id }}">

                            {{-- Estado --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                        Estado <span class="text-red-500">*</span>
                                    </label>
                                    <select name="status"
                                            id="status"
                                            required
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('status') border-red-300 @enderror">
                                        @foreach($statusOptions as $value => $label)
                                            <option value="{{ $value }}" {{ old('status', $shipment->status) === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('status')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="flex items-end">
                                    <div class="w-full p-3 bg-gray-50 rounded-md">
                                        <p class="text-xs text-gray-500 mb-1">Estado actual</p>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @switch($shipment->status)
                                                @case('planning')    bg-yellow-100 text-yellow-800 @break
                                                @case('loading')     bg-blue-100   text-blue-800   @break
                                                @case('loaded')      bg-green-100  text-green-800  @break
                                                @case('in_transit')  bg-purple-100 text-purple-800 @break
                                                @case('arrived')     bg-indigo-100 text-indigo-800 @break
                                                @case('discharging') bg-orange-100 text-orange-800 @break
                                                @case('completed')   bg-green-100  text-green-800  @break
                                                @case('delayed')     bg-red-100    text-red-800    @break
                                                @default             bg-gray-100   text-gray-800
                                            @endswitch">
                                            {{ ucfirst($shipment->status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════ --}}
                    {{-- SECCIÓN 2 — CONVOY (colapsable)            --}}
                    {{-- ═══════════════════════════════════════════ --}}
                    <div class="bg-white shadow rounded-lg"
                         x-data="{ open: {{ $errors->hasAny(['vessel_role','convoy_position','is_lead_vessel']) || $shipment->vessel_role !== 'single' ? 'true' : 'false' }} }">
                        <button type="button"
                                @click="open = !open"
                                class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 rounded-lg transition-colors">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Configuración de Convoy</h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Rol actual: <strong>{{ $vesselRoles[$shipment->vessel_role] ?? $shipment->vessel_role }}</strong>
                                </p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" class="border-t border-gray-100">
                            <div class="px-6 py-5">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label for="vessel_role" class="block text-sm font-medium text-gray-700 mb-1">
                                            Rol <span class="text-red-500">*</span>
                                        </label>
                                        <select name="vessel_role"
                                                id="vessel_role"
                                                required
                                                onchange="updateConvoyFields()"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('vessel_role') border-red-300 @enderror">
                                            @foreach($vesselRoles as $value => $label)
                                                <option value="{{ $value }}"
                                                        {{ old('vessel_role', $shipment->vessel_role) === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('vessel_role')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div id="convoy_position_group"
                                         style="display: {{ old('vessel_role', $shipment->vessel_role) !== 'single' ? 'block' : 'none' }}">
                                        <label for="convoy_position" class="block text-sm font-medium text-gray-700 mb-1">
                                            Posición
                                        </label>
                                        <input type="number"
                                               name="convoy_position"
                                               id="convoy_position"
                                               value="{{ old('convoy_position', $shipment->convoy_position) }}"
                                               min="1" max="20"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('convoy_position') border-red-300 @enderror">
                                        @error('convoy_position')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-gray-500">Orden en el convoy (1-20)</p>
                                    </div>

                                    <div id="is_lead_vessel_group"
                                         style="display: {{ old('vessel_role', $shipment->vessel_role) !== 'single' ? 'block' : 'none' }}">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Líder</label>
                                        <div class="mt-2">
                                            <label class="inline-flex items-center">
                                                <input type="checkbox"
                                                       name="is_lead_vessel"
                                                       id="is_lead_vessel"
                                                       value="1"
                                                       {{ old('is_lead_vessel', $shipment->is_lead_vessel) ? 'checked' : '' }}
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                                <span class="ml-2 text-sm text-gray-700">Es embarcación líder</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════ --}}
                    {{-- SECCIÓN 3 — TRASBORDO (colapsable)          --}}
                    {{-- ═══════════════════════════════════════════ --}}
                    <div class="bg-white shadow rounded-lg"
                         x-data="{ open: {{ $errors->hasAny(['origin_manifest_id','origin_transport_doc']) || $shipment->origin_manifest_id || $shipment->origin_transport_doc ? 'true' : 'false' }} }">
                        <button type="button"
                                @click="open = !open"
                                class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 rounded-lg transition-colors">
                            <div class="flex items-center gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">Información de Trasbordo</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">Solo si la carga viene de otro medio de transporte</p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    Opcional
                                </span>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" class="border-t border-gray-100">
                            <div class="px-6 py-5">
                                <p class="text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded px-3 py-2 mb-4">
                                    Complete estos campos <strong>únicamente</strong> si este shipment corresponde a un trasbordo.
                                    Serán utilizados para la declaración MIC/DTA ante AFIP.
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="origin_manifest_id" class="block text-sm font-medium text-gray-700 mb-1">
                                            ID Manifiesto de Origen
                                        </label>
                                        <input type="text"
                                               name="origin_manifest_id"
                                               id="origin_manifest_id"
                                               maxlength="20"
                                               value="{{ old('origin_manifest_id', $shipment->origin_manifest_id) }}"
                                               placeholder="Ej: MAN-2024-001234"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('origin_manifest_id') border-red-300 @enderror">
                                        @error('origin_manifest_id')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-gray-400">Máx. 20 caracteres</p>
                                    </div>
                                    <div>
                                        <label for="origin_transport_doc" class="block text-sm font-medium text-gray-700 mb-1">
                                            Documento de Transporte de Origen
                                        </label>
                                        <input type="text"
                                               name="origin_transport_doc"
                                               id="origin_transport_doc"
                                               maxlength="39"
                                               value="{{ old('origin_transport_doc', $shipment->origin_transport_doc) }}"
                                               placeholder="Ej: BL-ARG-2024-987654"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('origin_transport_doc') border-red-300 @enderror">
                                        @error('origin_transport_doc')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-gray-400">BL, CRT, AWB, etc. Máx. 39 caracteres</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════ --}}
                    {{-- SECCIÓN 4 — NOTAS (colapsable)             --}}
                    {{-- ═══════════════════════════════════════════ --}}
                    <div class="bg-white shadow rounded-lg"
                         x-data="{ open: {{ $errors->hasAny(['special_instructions','handling_notes']) || $shipment->special_instructions || $shipment->handling_notes ? 'true' : 'false' }} }">
                        <button type="button"
                                @click="open = !open"
                                class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 rounded-lg transition-colors">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Notas e Instrucciones</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Instrucciones especiales y notas operativas</p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" class="border-t border-gray-100">
                            <div class="px-6 py-5 space-y-4">
                                <div>
                                    <label for="special_instructions" class="block text-sm font-medium text-gray-700 mb-1">
                                        Instrucciones Especiales
                                    </label>
                                    <textarea name="special_instructions"
                                              id="special_instructions"
                                              rows="3"
                                              placeholder="Instrucciones específicas para este shipment..."
                                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('special_instructions', $shipment->special_instructions) }}</textarea>
                                </div>
                                <div>
                                    <label for="handling_notes" class="block text-sm font-medium text-gray-700 mb-1">
                                        Notas de Manejo
                                    </label>
                                    <textarea name="handling_notes"
                                              id="handling_notes"
                                              rows="3"
                                              placeholder="Notas operativas para el manejo de la carga..."
                                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('handling_notes', $shipment->handling_notes) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Botones --}}
                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('company.shipments.show', $shipment) }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancelar
                        </a>
                        <div class="flex items-center gap-3">
                            @if(in_array($shipment->status, ['planning', 'loading']))
                                <button type="button"
                                        onclick="duplicateShipment()"
                                        class="inline-flex items-center px-4 py-2 border border-blue-300 rounded-md shadow-sm text-sm font-medium text-blue-700 bg-white hover:bg-blue-50">
                                    Duplicar
                                </button>
                            @endif
                            <button type="submit"
                                    id="submit-btn"
                                    class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Guardar Cambios
                            </button>
                        </div>
                    </div>

                </form>

            @else
                {{-- Sin permisos --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400 mr-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="text-sm font-medium text-yellow-800">Sin permisos para Cargas</h3>
                            <p class="mt-1 text-sm text-yellow-700">Su empresa no tiene el rol "Cargas" asignado.</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── Convoy fields ─────────────────────────────────────────────
        window.updateConvoyFields = function () {
            const role = document.getElementById('vessel_role').value;
            const posGroup  = document.getElementById('convoy_position_group');
            const leadGroup = document.getElementById('is_lead_vessel_group');
            const pos       = document.getElementById('convoy_position');
            const lead      = document.getElementById('is_lead_vessel');

            if (role === 'single') {
                posGroup.style.display  = 'none';
                leadGroup.style.display = 'none';
                if (pos)  pos.value    = '';
                if (lead) lead.checked = false;
            } else {
                posGroup.style.display  = 'block';
                leadGroup.style.display = 'block';
                if (pos && !pos.value) pos.value = role === 'lead' ? '1' : '2';
                if (lead && role === 'lead') lead.checked = true;
            }
        };

        // ── Submit con spinner ────────────────────────────────────────
        const form = document.getElementById('shipment-form');
        if (form) {
            form.addEventListener('submit', function () {
                const btn = document.getElementById('submit-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Guardando...';
                }
            });
        }

        // ── Duplicar shipment ─────────────────────────────────────────
        window.duplicateShipment = function () {
            if (confirm('¿Desea crear una copia de este shipment?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("company.shipments.duplicate", $shipment) }}';
                const token = document.createElement('input');
                token.type  = 'hidden';
                token.name  = '_token';
                token.value = '{{ csrf_token() }}';
                form.appendChild(token);
                document.body.appendChild(form);
                form.submit();
            }
        };

    });
    </script>
    @endpush

</x-app-layout>