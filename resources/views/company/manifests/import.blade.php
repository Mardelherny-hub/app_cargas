<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📥 Importar Manifiestos
            </h2>
            <a href="{{ route('company.manifests.index') }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                ← Volver a Manifiestos
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Mensajes de resultado (importación encolada / errores) --}}
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-4 mb-6">
                    {{ session('success') }}
                    <a href="{{ route('company.manifests.import.history') }}" class="underline font-medium ml-1">
                        Ver historial →
                    </a>
                </div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-4 mb-6">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Información de formatos soportados -->
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            🤖 Auto-detección de Formato Activada
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>
                                El sistema detectará automáticamente el formato del archivo.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de importación -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📤 Subir Archivo de Manifiesto</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Seleccione el archivo de manifiesto para importar. El sistema detectará automáticamente el formato.
                    </p>
                </div>

                <form action="{{ route('company.manifests.import.store') }}" 
                      method="POST" 
                      enctype="multipart/form-data" 
                      class="px-6 py-4 space-y-6">
                    @csrf

                    {{-- Overlay de espera durante la importación --}}
                    <div id="import-overlay"
                         style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(17,24,39,0.75); align-items:center; justify-content:center;">
                        <div style="background:#fff; border-radius:0.75rem; padding:2rem 2.5rem; max-width:24rem; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.3);">
                            <svg class="animate-spin" style="height:2.5rem; width:2.5rem; margin:0 auto 1rem; color:#2563eb;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p style="font-weight:600; color:#111827; margin-bottom:0.5rem;">Enviando archivo…</p>
                            <p style="font-size:0.875rem; color:#6b7280;">Se está poniendo en cola para procesar.<br>En un momento vas a ver el resultado.</p>
                        </div>
                    </div>

                    <!-- Selección de archivo -->
                    <div>
                        <label for="manifest_file" class="block text-sm font-medium text-gray-700 mb-2">
                            📎 Archivo de Manifiesto *
                        </label>
                        <input type="file" 
                               id="manifest_file" 
                               name="manifest_file" 
                               required
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        
                        @error('manifest_file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        
                        <p class="mt-1 text-xs text-gray-500">
                            Tamaño máximo: 10MB. El sistema detectará automáticamente el formato del archivo.
                        </p>
                    </div>

                    <!-- Selección de embarcación -->
                    <div>
                        <label for="vessel_id" class="block text-sm font-medium text-gray-700 mb-2">
                            🚢 Embarcación *
                        </label>
                        <select name="vessel_id" id="vessel_id" required 
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleccione una embarcación...</option>
                            @foreach($vessels as $vessel)
                                <option value="{{ $vessel->id }}" {{ old('vessel_id') == $vessel->id ? 'selected' : '' }}>
                                    {{ $vessel->name }}
                                    @if($vessel->registration_number)
                                        ({{ $vessel->registration_number }})
                                    @endif
                                    @if($vessel->cargo_capacity_tons)
                                        - {{ number_format($vessel->cargo_capacity_tons) }}t
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('vessel_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">
                            Seleccione la embarcación del viaje: se usa siempre la que elija aquí. En MSC, su nombre debe coincidir con VESSEL NAME del archivo o la importación se rechaza. En los demás formatos no se compara con el nombre que informe el archivo.
                        </p>
                        <p id="vessel-proposal" class="mt-1 text-sm text-blue-700" style="display:none;"></p>
                    </div>

                    <!-- Destino de la importación -->
                    <div>
                        <span class="block text-sm font-medium text-gray-700 mb-2">🧭 Destino</span>
                        <div class="flex flex-wrap gap-6">
                            <label class="inline-flex items-center">
                                <input type="radio" name="import_mode" value="create" class="text-blue-600 focus:ring-blue-500"
                                       {{ old('target_voyage_id') ? '' : 'checked' }}>
                                <span class="ml-2 text-sm text-gray-700">Crear viaje nuevo</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="import_mode" value="add" class="text-blue-600 focus:ring-blue-500"
                                       {{ old('target_voyage_id') ? 'checked' : '' }}
                                       {{ $planningVoyages->isEmpty() ? 'disabled' : '' }}>
                                <span class="ml-2 text-sm text-gray-700">Agregar a un viaje existente</span>
                            </label>
                        </div>
                        @if($planningVoyages->isEmpty())
                            <p class="mt-1 text-xs text-gray-500">No hay viajes en planificación para agregar archivos.</p>
                        @endif

                        <div id="target-voyage-block" class="mt-4" style="display:none;">
                            <label for="target_voyage_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Viaje en planificación *
                            </label>
                            <select name="target_voyage_id" id="target_voyage_id"
                                    class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione un viaje...</option>
                                @foreach($planningVoyages as $planningVoyage)
                                    <option value="{{ $planningVoyage->id }}" {{ old('target_voyage_id') == $planningVoyage->id ? 'selected' : '' }}>
                                        {{ $planningVoyage->voyage_number }}
                                        — {{ $planningVoyage->leadVessel->name ?? 'sin embarcación líder' }}
                                        {{ $planningVoyage->is_convoy ? '(convoy, ' . $planningVoyage->shipments->count() . ' embarcaciones)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('target_voyage_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                Se agregan los conocimientos del archivo a la carga de la embarcación elegida. Los datos
                                generales del viaje no cambian y los conocimientos que ya existen en el viaje se saltean.
                            </p>

                            <div id="convoy-block" class="mt-4 bg-yellow-50 border border-yellow-200 rounded-md p-4" style="display:none;">
                                <p class="text-sm text-yellow-800">
                                    La embarcación no está en este viaje y el viaje no es un convoy. Para agregarla, el viaje
                                    se convierte en convoy. Elija la embarcación líder; puede ser una embarcación sin carga.
                                </p>
                                <label for="convoy_leader_vessel_id" class="block text-sm font-medium text-gray-700 mt-3 mb-2">
                                    Embarcación líder del convoy *
                                </label>
                                <select name="convoy_leader_vessel_id" id="convoy_leader_vessel_id"
                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccione el líder...</option>
                                    @foreach($vessels as $vessel)
                                        <option value="{{ $vessel->id }}" {{ old('convoy_leader_vessel_id') == $vessel->id ? 'selected' : '' }}>
                                            {{ $vessel->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-yellow-800">
                                    Si revierte esta importación, el viaje vuelve a ser como estaba antes de convertirlo.
                                </p>
                            </div>

                            <div id="target-error" class="mt-3 bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm" style="display:none;"></div>
                        </div>
                    </div>

                    <!-- Tipo de operación CUSCAR -->
                    <div>
                        <label for="operation_type"
                               class="block text-sm font-medium text-gray-700 mb-2">
                            ↔️ Tipo de operación
                            <span class="text-gray-500 font-normal">(requerido para CUSCAR)</span>
                        </label>

                        <select name="operation_type"
                                id="operation_type"
                                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleccione para archivos CUSCAR...</option>
                            <option value="import" {{ old('operation_type') === 'import' ? 'selected' : '' }}>
                                Importación
                            </option>
                            <option value="export" {{ old('operation_type') === 'export' ? 'selected' : '' }}>
                                Exportación
                            </option>
                        </select>

                        @error('operation_type')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="mt-1 text-xs text-gray-500">
                            CUSCAR informa los puertos físicos de carga y descarga, pero esos
                            puertos no determinan por sí solos si la operación comercial es
                            una importación o una exportación. Para CUSCAR debe seleccionar
                            explícitamente el tipo de operación. Los demás formatos conservan
                            su criterio propio.
                        </p>
                    </div>

                    <!-- Número de viaje -->
                    <div data-create-only>
                        <label for="voyage_number"
                               class="block text-sm font-medium text-gray-700 mb-2">
                            🧭 Número de viaje <span class="text-gray-500 font-normal">(opcional)</span>
                        </label>

                        <input type="text"
                               id="voyage_number"
                               name="voyage_number"
                               value="{{ old('voyage_number') }}"
                               maxlength="100"
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">

                        @error('voyage_number')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="mt-1 text-xs text-gray-500">
                            Si lo deja vacío, se utilizará el número informado por el archivo.
                            Si la fuente tampoco informa un número de viaje, la
                            importación no podrá completarse en los formatos que lo requieren.
                        </p>
                    </div>

                    <!-- Fecha/hora de salida -->
                    <div data-create-only>
                        <label for="departure_date"
                               class="block text-sm font-medium text-gray-700 mb-2">
                            📅 Fecha y hora de salida <span class="text-gray-500 font-normal">(opcional)</span>
                        </label>

                        <input type="datetime-local"
                               id="departure_date"
                               name="departure_date"
                               value="{{ old('departure_date') }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">

                        @error('departure_date')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="mt-1 text-xs text-gray-500">
                            Si lo deja completamente vacío, se conservará la fecha y hora
                            informada por la fuente. Si la fuente tampoco informa la salida,
                            quedará sin informar. Si decide reemplazarla, debe ingresar
                            fecha y hora completas; el sistema no inventa una hora.
                        </p>
                    </div>

                    <!-- Fecha de carga de los conocimientos -->
                    <div>
                        <label for="loading_date"
                               class="block text-sm font-medium text-gray-700 mb-2">
                            📅 Fecha de Carga <span class="text-gray-500 font-normal">(opcional)</span>
                        </label>

                        <input type="date"
                               id="loading_date"
                               name="loading_date"
                               value="{{ old('loading_date') }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">

                        @error('loading_date')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="mt-1 text-xs text-gray-500">
                            Si lo deja vacío, se conservará la fecha de carga informada
                            por la fuente. Si se completa, se aplicará a los
                            conocimientos importados y tendrá prioridad sobre la fuente.
                        </p>
                    </div>

                    <!-- Fecha de descarga de los conocimientos -->
                    <div>
                        <label for="discharge_date"
                               class="block text-sm font-medium text-gray-700 mb-2">
                            📅 Fecha de Descarga <span class="text-gray-500 font-normal">(opcional)</span>
                        </label>

                        <input type="date"
                               id="discharge_date"
                               name="discharge_date"
                               value="{{ old('discharge_date') }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">

                        @error('discharge_date')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="mt-1 text-xs text-gray-500">
                            Si lo deja vacío, se conservará la fecha informada por la fuente.
                            Si se completa, se aplicará a los conocimientos importados
                            y como llegada estimada del viaje, con prioridad sobre la fuente.
                            Algunos formatos pueden requerir este dato cuando el archivo
                            no informa una fecha estimada de llegada.
                        </p>
                    </div>

                    <!-- Botón de importación -->
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            <strong>Importante:</strong>
                            <span data-create-only>El archivo será procesado y se creará un nuevo viaje con los datos importados.</span>
                            <span data-add-only style="display:none;">El archivo será procesado y sus conocimientos se agregarán al viaje elegido.</span>
                        </div>
                        
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span>Importar Manifiesto</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Información de parsers disponibles -->
            @if(isset($supportedFormats) && $supportedFormats)
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($supportedFormats as $info)
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-2">
                        {{ $info['icon'] ?? '📄' }} {{ $info['name'] ?? 'Formato de manifiesto' }}
                    </h4>
                    <p class="text-sm text-gray-600 mb-2">
                        {{ $info['description'] ?? 'Formato de manifiesto' }}
                    </p>
                    <div class="text-xs text-green-600">
                        ✅ Formato disponible
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <!-- Información por defecto si no hay supportedFormats -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- KLine DAT -->
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-2">🚢 KLine.DAT</h4>
                    <p class="text-sm text-gray-600 mb-2">
                        Archivos de datos de K-Line en formato DAT
                    </p>
                    <div class="text-xs text-green-600">✅ Formato disponible</div>
                </div>

                <!-- PARANA Excel -->
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-2">📊 PARANA.xlsx</h4>
                    <p class="text-sm text-gray-600 mb-2">
                        Formato Excel MAERSK con 73 columnas
                    </p>
                    <div class="text-xs text-green-600">✅ Formato disponible</div>
                </div>

                <!-- Guaran CSV -->
                <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                    <h4 class="font-medium text-gray-900 mb-2">📝 Guaran.csv</h4>
                    <p class="text-sm text-gray-600 mb-2">
                        CSV consolidado multi-línea
                    </p>
                    <div class="text-xs text-green-600">✅ Formato disponible</div>
                </div>
            </div>
            @endif

            <!-- Historial reciente -->
            <div class="mt-6">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">📜 Importaciones Recientes</h3>
                    </div>
                    <div class="px-6 py-4">
                        @if(($recentImports ?? collect())->isNotEmpty())
                            <ul class="divide-y divide-gray-100 mb-3">
                                @foreach($recentImports as $recent)
                                    <li class="py-2 text-sm">
                                        <a href="{{ route('company.manifests.import.show', $recent) }}" class="font-medium text-gray-900 hover:text-blue-600 break-all">
                                            {{ $recent->display_file_name }}
                                        </a>
                                        <div class="text-xs text-gray-500">
                                            {{ $recent->format_label }}
                                            · {{ optional($recent->created_at)->format('d/m/Y H:i') }}
                                            @if($recent->voyage)
                                                · Viaje {{ $recent->voyage->voyage_number }}
                                            @endif
                                            · {{ $recent->status_badge['text'] ?? $recent->status }}
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <p class="text-sm text-gray-600">
                            @if(($recentImports ?? collect())->isEmpty())
                                No hay importaciones recientes.
                            @endif
                            <a href="{{ route('company.manifests.import.history') }}" class="text-blue-600 hover:text-blue-500">
                                Ver historial completo →
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@push('scripts')
    <script>
        (function () {
            const form = document.querySelector('form[action="{{ route('company.manifests.import.store') }}"]');
            if (!form) return;

            const overlay = document.getElementById('import-overlay');
            const submitBtn = form.querySelector('button[type="submit"]');

            form.addEventListener('submit', function (e) {
                // Evitar doble envío
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                if (overlay) {
                    overlay.style.display = 'flex';
                }
            });

            // Destino: crear viaje nuevo o agregar a uno existente
            const modeInputs = form.querySelectorAll('input[name="import_mode"]');
            const targetBlock = document.getElementById('target-voyage-block');
            const targetSelect = document.getElementById('target_voyage_id');
            const vesselSelect = document.getElementById('vessel_id');
            const convoyBlock = document.getElementById('convoy-block');
            const leaderSelect = document.getElementById('convoy_leader_vessel_id');
            const targetError = document.getElementById('target-error');
            const proposal = document.getElementById('vessel-proposal');
            const checkUrl = @json(route('company.manifests.import.check-target'));
            const detectUrl = @json(route('company.manifests.import.detect-vessel'));
            const csrf = form.querySelector('input[name="_token"]').value;
            let formatSupportsTarget = true;

            function isAddMode() {
                const checked = form.querySelector('input[name="import_mode"]:checked');
                return checked && checked.value === 'add';
            }

            function showTargetError(message) {
                targetError.textContent = message || '';
                targetError.style.display = message ? 'block' : 'none';
                submitBtn.disabled = !!message;
                submitBtn.style.opacity = message ? '0.5' : '';
            }

            function applyMode() {
                const add = isAddMode();
                targetBlock.style.display = add ? 'block' : 'none';
                targetSelect.required = add;
                form.querySelectorAll('[data-create-only]').forEach(el => {
                    el.style.display = add ? 'none' : '';
                    // Con viaje destino no se envían número de viaje ni salida.
                    el.querySelectorAll('input').forEach(input => input.disabled = add);
                });
                form.querySelectorAll('[data-add-only]').forEach(el => el.style.display = add ? '' : 'none');
                if (!add) {
                    targetSelect.value = '';
                    leaderSelect.value = '';
                    convoyBlock.style.display = 'none';
                    leaderSelect.required = false;
                    showTargetError(null);
                }
                checkTarget();
            }

            async function checkTarget() {
                if (!isAddMode()) return;
                if (!formatSupportsTarget) {
                    showTargetError('Este formato todavía no admite agregar archivos a un viaje existente.');
                    return;
                }
                if (!targetSelect.value || !vesselSelect.value) {
                    convoyBlock.style.display = 'none';
                    showTargetError(null);
                    return;
                }
                const params = new URLSearchParams({
                    target_voyage_id: targetSelect.value,
                    vessel_id: vesselSelect.value,
                });
                if (leaderSelect.value) params.append('convoy_leader_vessel_id', leaderSelect.value);

                try {
                    const response = await fetch(checkUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json' },
                    });
                    const check = await response.json();
                    convoyBlock.style.display = check.needs_convoy ? 'block' : 'none';
                    leaderSelect.required = !!check.needs_convoy;
                    if (!check.needs_convoy) leaderSelect.value = '';
                    // Falta elegir el líder: el bloque de convoy ya lo explica.
                    const pendingLeader = check.needs_convoy && !leaderSelect.value;
                    showTargetError(check.allowed || pendingLeader ? null : check.error);
                    if (pendingLeader) {
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.5';
                    }
                } catch (error) {
                    showTargetError('No se pudo verificar el viaje elegido. Intente nuevamente.');
                }
            }

            async function detectVessel(file) {
                proposal.style.display = 'none';
                formatSupportsTarget = true;
                const data = new FormData();
                data.append('manifest_file', file);
                data.append('_token', csrf);

                try {
                    const response = await fetch(detectUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: data,
                    });
                    if (!response.ok) return;
                    const result = await response.json();
                    formatSupportsTarget = !!result.supports_target;

                    if (result.vessel_id) {
                        vesselSelect.value = String(result.vessel_id);
                        proposal.textContent = 'Embarcación propuesta según el archivo: ' + result.vessel_name
                            + '. Confírmela o elija otra.';
                    } else if (result.vessel_name) {
                        proposal.textContent = 'El archivo informa la embarcación «' + result.vessel_name
                            + '», que no está registrada en su empresa. Elija la embarcación.';
                    } else {
                        proposal.textContent = '';
                    }
                    proposal.style.display = proposal.textContent ? 'block' : 'none';
                } catch (error) {
                    // Sin propuesta: el usuario elige la embarcación.
                } finally {
                    checkTarget();
                }
            }

            modeInputs.forEach(input => input.addEventListener('change', applyMode));
            targetSelect.addEventListener('change', checkTarget);
            vesselSelect.addEventListener('change', checkTarget);
            leaderSelect.addEventListener('change', checkTarget);
            applyMode();

            // Info de archivo seleccionado (solo consola, diagnóstico)
            const fileInput = document.getElementById('manifest_file');
            if (fileInput) {
                fileInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (file) {
                        console.log('Archivo:', file.name, (file.size / 1024 / 1024).toFixed(2) + 'MB');
                        detectVessel(file);
                    }
                });
            }
        })();
    </script>
    @endpush
</x-app-layout>
