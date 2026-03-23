<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('company.voyages.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear Nuevo Viaje</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Complete los datos requeridos por AFIP y DNA Paraguay</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

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

            <form method="POST" action="{{ route('company.voyages.store') }}" id="voyage-form">
                @csrf

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- SECCIÓN 1 — DATOS OBLIGATORIOS WS                  --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="bg-white shadow rounded-lg border-l-4 border-blue-500">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Datos del Viaje</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Campos requeridos por AFIP y DNA Paraguay</p>
                    </div>
                    <div class="px-6 py-5 space-y-5">

                        {{-- Número de viaje + Tipo de viaje --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="voyage_number" class="block text-sm font-medium text-gray-700 mb-1">
                                    Número de Viaje <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="voyage_number"
                                       id="voyage_number"
                                       value="{{ old('voyage_number') }}"
                                       required
                                       maxlength="50"
                                       placeholder="Ej: V001-2025"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('voyage_number') border-red-300 @enderror">
                                @error('voyage_number')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="voyage_type" class="block text-sm font-medium text-gray-700 mb-1">
                                    Tipo de Viaje <span class="text-red-500">*</span>
                                </label>
                                <select name="voyage_type"
                                        id="voyage_type"
                                        required
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('voyage_type') border-red-300 @enderror">
                                    <option value="">Seleccione un tipo</option>
                                    <option value="single_vessel" {{ old('voyage_type') === 'single_vessel' ? 'selected' : '' }}>Embarcación única</option>
                                    <option value="convoy"        {{ old('voyage_type') === 'convoy'        ? 'selected' : '' }}>Convoy</option>
                                    <option value="fleet"         {{ old('voyage_type') === 'fleet'         ? 'selected' : '' }}>Flota</option>
                                </select>
                                @error('voyage_type')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Embarcación + Capitán --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="lead_vessel_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Embarcación Líder <span class="text-red-500">*</span>
                                </label>
                                <select name="lead_vessel_id"
                                        id="lead_vessel_id"
                                        required
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('lead_vessel_id') border-red-300 @enderror">
                                    <option value="">Seleccione una embarcación</option>
                                    @foreach($vessels as $vessel)
                                        <option value="{{ $vessel->id }}" {{ old('lead_vessel_id') == $vessel->id ? 'selected' : '' }}>
                                            {{ $vessel->name }}{{ $vessel->vesselType ? ' — ' . $vessel->vesselType->name : '' }}
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
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('captain_id') border-red-300 @enderror">
                                    <option value="">Sin capitán asignado</option>
                                    @foreach($captains as $captain)
                                        <option value="{{ $captain->id }}" {{ old('captain_id') == $captain->id ? 'selected' : '' }}>
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
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('origin_country_id') border-red-300 @enderror">
                                        <option value="">Seleccione país</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}" {{ old('origin_country_id') == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }} ({{ $country->iso_code }})
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
                                            disabled
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('origin_port_id') border-red-300 @enderror">
                                        <option value="">Primero seleccione un país</option>
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
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('destination_country_id') border-red-300 @enderror">
                                        <option value="">Seleccione país</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}" {{ old('destination_country_id') == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }} ({{ $country->iso_code }})
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
                                            disabled
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('destination_port_id') border-red-300 @enderror">
                                        <option value="">Primero seleccione un país</option>
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
                                    Fecha y Hora de Salida <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local"
                                       name="departure_date"
                                       id="departure_date"
                                       value="{{ old('departure_date') }}"
                                       required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('departure_date') border-red-300 @enderror">
                                @error('departure_date')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="estimated_arrival_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha y Hora Estimada de Llegada <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local"
                                       name="estimated_arrival_date"
                                       id="estimated_arrival_date"
                                       value="{{ old('estimated_arrival_date') }}"
                                       required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('estimated_arrival_date') border-red-300 @enderror">
                                @error('estimated_arrival_date')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Estado de carga (selector único → mapea is_empty_transport + has_cargo_onboard) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Estado de Carga <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label id="label-con-carga"
                                       class="flex items-start gap-3 p-3 border-2 border-blue-500 bg-blue-50 rounded-lg cursor-pointer transition-all">
                                    <input type="radio" name="cargo_status" value="con_carga"
                                           class="mt-0.5 text-blue-600 focus:ring-blue-500" checked>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Con carga</p>
                                        <p class="text-xs text-gray-500">Lleva mercadería o contenedores (incluye contenedores vacíos)</p>
                                    </div>
                                </label>
                                <label id="label-lastre"
                                       class="flex items-start gap-3 p-3 border-2 border-gray-200 bg-white rounded-lg cursor-pointer transition-all">
                                    <input type="radio" name="cargo_status" value="lastre"
                                           class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">En lastre</p>
                                        <p class="text-xs text-gray-500">Sin ninguna carga ni contenedores a bordo</p>
                                    </div>
                                </label>
                            </div>
                            {{-- Alerta lastre --}}
                            <div id="lastre-warning" class="hidden mt-3 p-3 bg-amber-50 border border-amber-200 rounded-md">
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
                            <input type="hidden" name="is_empty_transport" id="is_empty_transport" value="N">
                            <input type="hidden" name="has_cargo_onboard"  id="has_cargo_onboard"  value="S">
                        </div>

                        {{-- Tipo de Carga --}}
                        <div>
                            <label for="cargo_type" class="block text-sm font-medium text-gray-700 mb-1">
                                Tipo de Operación <span class="text-red-500">*</span>
                            </label>
                            <select name="cargo_type"
                                    id="cargo_type"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('cargo_type') border-red-300 @enderror">
                                <option value="">Seleccione tipo de operación</option>
                                <option value="export"        {{ old('cargo_type') === 'export'        ? 'selected' : '' }}>Exportación</option>
                                <option value="import"        {{ old('cargo_type') === 'import'        ? 'selected' : '' }}>Importación</option>
                                <option value="transit"       {{ old('cargo_type') === 'transit'       ? 'selected' : '' }}>Tránsito</option>
                                <option value="transshipment" {{ old('cargo_type') === 'transshipment' ? 'selected' : '' }}>Trasbordo</option>
                                <option value="cabotage"      {{ old('cargo_type') === 'cabotage'      ? 'selected' : '' }}>Cabotaje</option>
                            </select>
                            @error('cargo_type')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- SECCIÓN 2 — INFORMACIÓN ADICIONAL (colapsable)     --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="bg-white shadow rounded-lg" x-data="{ open: {{ $errors->hasAny(['internal_reference', 'notes']) ? 'true' : 'false' }} }">
                    <button type="button"
                            @click="open = !open"
                            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 rounded-lg transition-colors">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Información Adicional</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Referencia interna y observaciones</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" class="border-t border-gray-100">
                        <div class="px-6 py-5 space-y-4">
                            <div>
                                <label for="internal_reference" class="block text-sm font-medium text-gray-700 mb-1">
                                    Referencia Interna
                                </label>
                                <input type="text"
                                       name="internal_reference"
                                       id="internal_reference"
                                       value="{{ old('internal_reference') }}"
                                       maxlength="100"
                                       placeholder="Referencia opcional para uso interno"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                                    Observaciones
                                </label>
                                <textarea name="notes"
                                          id="notes"
                                          rows="3"
                                          placeholder="Información adicional sobre el viaje"
                                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════ --}}
                {{-- BOTONES                                             --}}
                {{-- ═══════════════════════════════════════════════════ --}}
                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('company.voyages.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button type="submit"
                            id="submit-btn"
                            class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Crear Viaje
                    </button>
                </div>

            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── Puertos por país ──────────────────────────────────────────
        const portsByCountry = @json($portsByCountry);

        function updatePortOptions(countryId, portSelect, oldValue) {
            portSelect.innerHTML = '';
            if (countryId && portsByCountry[countryId]) {
                portSelect.appendChild(new Option('Seleccione un puerto', ''));
                portsByCountry[countryId].forEach(function (port) {
                    const opt = new Option(`${port.name} (${port.code})`, port.id);
                    if (port.id == oldValue) opt.selected = true;
                    portSelect.appendChild(opt);
                });
                portSelect.disabled = false;
            } else {
                portSelect.appendChild(new Option('Primero seleccione un país', ''));
                portSelect.disabled = true;
            }
        }

        const originCountry      = document.getElementById('origin_country_id');
        const originPort         = document.getElementById('origin_port_id');
        const destinationCountry = document.getElementById('destination_country_id');
        const destinationPort    = document.getElementById('destination_port_id');

        originCountry.addEventListener('change', function () {
            updatePortOptions(this.value, originPort, '{{ old("origin_port_id") }}');
        });
        destinationCountry.addEventListener('change', function () {
            updatePortOptions(this.value, destinationPort, '{{ old("destination_port_id") }}');
        });

        // Restaurar puertos si hay old values (error de validación)
        if (originCountry.value) {
            updatePortOptions(originCountry.value, originPort, '{{ old("origin_port_id") }}');
        }
        if (destinationCountry.value) {
            updatePortOptions(destinationCountry.value, destinationPort, '{{ old("destination_port_id") }}');
        }

        // ── Validación de fechas ──────────────────────────────────────
        const departureInput = document.getElementById('departure_date');
        const arrivalInput   = document.getElementById('estimated_arrival_date');

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

        departureInput.addEventListener('change', function () {
            validateDates();
            // Auto-completar llegada (+2 días) si está vacía
            if (this.value && !arrivalInput.value) {
                const d = new Date(this.value);
                d.setDate(d.getDate() + 2);
                arrivalInput.value = d.toISOString().slice(0, 16);
                validateDates();
            }
        });
        arrivalInput.addEventListener('change', validateDates);

        // ── Estado de carga (radio → hidden fields) ───────────────────
        const radios          = document.querySelectorAll('input[name="cargo_status"]');
        const hiddenEmpty     = document.getElementById('is_empty_transport');
        const hiddenCargo     = document.getElementById('has_cargo_onboard');
        const lastreWarning   = document.getElementById('lastre-warning');
        const labelConCarga   = document.getElementById('label-con-carga');
        const labelLastre     = document.getElementById('label-lastre');

        // Restaurar estado si hay old value
        @if(old('is_empty_transport') === 'S')
            document.querySelector('input[name="cargo_status"][value="lastre"]').checked = true;
        @endif

        function updateCargoStatus() {
            const isLastre = document.querySelector('input[name="cargo_status"]:checked').value === 'lastre';

            hiddenEmpty.value   = isLastre ? 'S' : 'N';
            hiddenCargo.value   = isLastre ? 'N' : 'S';
            lastreWarning.classList.toggle('hidden', !isLastre);

            // Estilo visual del radio seleccionado
            labelConCarga.classList.toggle('border-blue-500', !isLastre);
            labelConCarga.classList.toggle('bg-blue-50',      !isLastre);
            labelConCarga.classList.toggle('border-gray-200',  isLastre);
            labelConCarga.classList.toggle('bg-white',         isLastre);

            labelLastre.classList.toggle('border-blue-500',  isLastre);
            labelLastre.classList.toggle('bg-blue-50',       isLastre);
            labelLastre.classList.toggle('border-gray-200', !isLastre);
            labelLastre.classList.toggle('bg-white',        !isLastre);
        }

        radios.forEach(r => r.addEventListener('change', updateCargoStatus));
        updateCargoStatus(); // Estado inicial

        // ── Validación al enviar ──────────────────────────────────────
        document.getElementById('voyage-form').addEventListener('submit', function (e) {
            if (!validateDates()) {
                e.preventDefault();
                return;
            }

            // Puertos pertenecen al país correcto
            const oPort = originPort.options[originPort.selectedIndex];
            const dPort = destinationPort.options[destinationPort.selectedIndex];

            if (originPort.value && destinationPort.value && originPort.value === destinationPort.value) {
                e.preventDefault();
                alert('El puerto de origen y destino no pueden ser el mismo.');
                return;
            }

            // Spinner en botón
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Creando...';
        });

    });
    </script>
    @endpush

</x-app-layout>