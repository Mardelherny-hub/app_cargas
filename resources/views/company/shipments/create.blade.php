<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Crear Nueva Carga
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">Shipment para el viaje {{ $voyage->voyage_number }}</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('company.voyages.show', $voyage) }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Volver al Viaje
                </a>
                <a href="{{ route('company.shipments.index') }}"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Lista de Cargas
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Errores --}}
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

            {{-- Datos heredados del Voyage --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-5">
                <h3 class="text-base font-semibold text-blue-900 mb-3">
                    📦 Datos del Viaje: {{ $voyage->voyage_number }}
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs font-medium text-blue-700">Embarcación</p>
                        <p class="text-sm text-blue-600 font-medium">{{ $formData['voyageInfo']['vessel_name'] }}</p>
                        <p class="text-xs text-blue-500">{{ $formData['voyageInfo']['vessel_type'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-blue-700">Capitán</p>
                        <p class="text-sm text-blue-600 font-medium">{{ $formData['voyageInfo']['captain_name'] }}</p>
                        <p class="text-xs text-blue-500">Lic: {{ $formData['voyageInfo']['captain_license'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-blue-700">Cap. Carga</p>
                        <p class="text-sm text-blue-600 font-medium">{{ number_format($formData['voyageInfo']['cargo_capacity'], 2) }} Tons</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-blue-700">Cap. Contenedores</p>
                        <p class="text-sm text-blue-600 font-medium">{{ $formData['voyageInfo']['container_capacity'] }} TEU</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-blue-700 bg-blue-100 rounded px-3 py-2">
                    ✅ Las capacidades se heredan automáticamente de la embarcación del viaje.
                </p>
            </div>

            <form method="POST" action="{{ route('company.shipments.store') }}" id="shipment-form">
                @csrf
                <input type="hidden" name="voyage_id" value="{{ $voyage->id }}">
                <input type="hidden" name="is_lead_vessel" id="is_lead_vessel" value="0">

                {{-- ═══════════════════════════════════════════ --}}
                {{-- SECCIÓN 1 — DATOS OBLIGATORIOS              --}}
                {{-- ═══════════════════════════════════════════ --}}
                <div class="bg-white shadow rounded-lg border-l-4 border-blue-500">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Datos de la Carga</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Información principal del shipment</p>
                    </div>
                    <div class="px-6 py-5 space-y-5">

                        {{-- Número de Carga + Secuencia --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="shipment_number" class="block text-sm font-medium text-gray-700 mb-1">
                                    Número de Carga <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="shipment_number"
                                       id="shipment_number"
                                       value="{{ old('shipment_number', $nextShipmentNumber) }}"
                                       required
                                       maxlength="100"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('shipment_number') border-red-300 @enderror">
                                @error('shipment_number')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Secuencia en Viaje
                                </label>
                                <input type="text"
                                       value="Se calculará automáticamente"
                                       readonly
                                       class="block w-full rounded-md border-gray-200 bg-gray-50 text-gray-400 sm:text-sm cursor-not-allowed">
                                <p class="mt-1 text-xs text-gray-400">La secuencia se asigna según el orden de creación.</p>
                            </div>
                        </div>

                       {{-- Embarcación + Capitán --}}
                        @if($voyage->is_convoy)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="vessel_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        Embarcación <span class="text-red-500">*</span>
                                    </label>
                                    <select name="vessel_id"
                                            id="vessel_id"
                                            required
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('vessel_id') border-red-300 @enderror">
                                        <option value="">Seleccione una embarcación</option>
                                        @foreach($formData['vessels'] as $vessel)
                                            <option value="{{ $vessel['id'] }}"
                                                    {{ old('vessel_id') == $vessel['id'] ? 'selected' : '' }}>
                                                {{ $vessel['display_name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vessel_id')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Seleccione la barcaza para este shipment.</p>
                                </div>
                                <div class="flex items-end">
                                    <div class="w-full p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                        <p class="text-xs font-medium text-blue-700">Capitán del Convoy</p>
                                        <p class="text-sm text-blue-900 font-medium">{{ $voyage->captain->full_name ?? 'No asignado' }}</p>
                                        <p class="text-xs text-blue-600">Lic: {{ $voyage->captain->license_number ?? '-' }}</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-xs font-medium text-blue-700 mb-2">Embarcación y Capitán (heredados del viaje)</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-blue-600">Embarcación</p>
                                        <p class="text-sm text-blue-900 font-medium">{{ $voyage->leadVessel->name ?? 'No asignada' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-blue-600">Capitán</p>
                                        <p class="text-sm text-blue-900 font-medium">{{ $voyage->captain->full_name ?? 'No asignado' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                        {{-- Ocultos — el controlador los sobreescribe con los del voyage --}}
                        <input type="hidden" name="vessel_id"  value="{{ $voyage->lead_vessel_id }}">
                        <input type="hidden" name="captain_id" value="{{ $voyage->captain_id }}">

                        {{-- Estado --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                    Estado Inicial <span class="text-red-500">*</span>
                                </label>
                                <select name="status"
                                        id="status"
                                        required
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('status') border-red-300 @enderror">
                                    @foreach($formData['statusOptions'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('status', 'planning') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ═══════════════════════════════════════════ --}}
                {{-- SECCIÓN 2 — CONVOY (colapsable)            --}}
                {{-- ═══════════════════════════════════════════ --}}
                <div class="bg-white shadow rounded-lg"
                     x-data="{ open: {{ $errors->hasAny(['vessel_role','convoy_position','is_lead_vessel']) || old('vessel_role', 'single') !== 'single' ? 'true' : 'false' }} }">
                    <button type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 rounded-lg transition-colors">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Configuración de Convoy</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Rol y posición en el convoy — solo si aplica</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" class="border-t border-gray-100">
                        <div class="px-6 py-5 space-y-4">
                            {{-- Campo oculto por defecto — se sobreescribe si el usuario abre la sección --}}
                            <input type="hidden" name="vessel_role" id="vessel_role_hidden" value="single">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="vessel_role" class="block text-sm font-medium text-gray-700 mb-1">
                                        Rol en el Convoy <span class="text-red-500">*</span>
                                    </label>
                                    <select name="vessel_role"
                                            id="vessel_role"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('vessel_role') border-red-300 @enderror"
                                            onchange="toggleConvoyPosition(this.value)">
                                        @foreach($formData['vesselRoles'] as $value => $label)
                                            <option value="{{ $value }}"
                                                    {{ old('vessel_role', 'single') == $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vessel_role')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div id="convoy-position-field"
                                     style="display: {{ old('vessel_role', 'single') !== 'single' ? 'block' : 'none' }}">
                                    <label for="convoy_position" class="block text-sm font-medium text-gray-700 mb-1">
                                        Posición en Convoy
                                    </label>
                                    <input type="number"
                                           name="convoy_position"
                                           id="convoy_position"
                                           min="1"
                                           max="20"
                                           value="{{ old('convoy_position') }}"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('convoy_position') border-red-300 @enderror">
                                    @error('convoy_position')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Posición en la formación (1-20).</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════ --}}
                {{-- SECCIÓN 3 — TRASBORDO (colapsable)          --}}
                {{-- ═══════════════════════════════════════════ --}}
                <div class="bg-white shadow rounded-lg"
                     x-data="{ open: {{ $errors->hasAny(['origin_manifest_id','origin_transport_doc']) || old('origin_manifest_id') || old('origin_transport_doc') ? 'true' : 'false' }} }">
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
                                           value="{{ old('origin_manifest_id') }}"
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
                                           value="{{ old('origin_transport_doc') }}"
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
                     x-data="{ open: {{ $errors->hasAny(['special_instructions','handling_notes']) || old('special_instructions') || old('handling_notes') ? 'true' : 'false' }} }">
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
                                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('special_instructions') }}</textarea>
                            </div>
                            <div>
                                <label for="handling_notes" class="block text-sm font-medium text-gray-700 mb-1">
                                    Notas de Manejo
                                </label>
                                <textarea name="handling_notes"
                                          id="handling_notes"
                                          rows="3"
                                          placeholder="Notas operativas para el manejo de la carga..."
                                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('handling_notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Botones --}}
                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('company.voyages.show', $voyage) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button type="submit"
                            id="submit-btn"
                            class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Crear Shipment
                    </button>
                </div>

            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── Posición convoy ──────────────────────────────────────────
        window.toggleConvoyPosition = function (role) {
            const field = document.getElementById('convoy-position-field');
            field.style.display = role !== 'single' ? 'block' : 'none';
        };

        // ── Submit con spinner ────────────────────────────────────────
        document.getElementById('shipment-form').addEventListener('submit', function () {
            const btn = document.getElementById('submit-btn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Creando...';
            }
        });

    });
    </script>
    @endpush

</x-app-layout>