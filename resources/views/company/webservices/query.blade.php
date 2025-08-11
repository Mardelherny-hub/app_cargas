<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Consultar Estado de Manifiestos') }} - {{ $company->legal_name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('company.webservices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                    ← Dashboard
                </a>
                <a href="{{ route('company.webservices.history') }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    📋 Historial
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Estado del Certificado --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center space-x-3">
                        <div class="w-3 h-3 rounded-full 
                            {{ $certificateStatus['status_color'] === 'green' ? 'bg-green-500' : 
                               ($certificateStatus['status_color'] === 'yellow' ? 'bg-yellow-500' : 'bg-red-500') }}">
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                🔐 Certificado Digital: {{ $certificateStatus['status_text'] }}
                            </h3>
                            @if($certificateStatus['expires_at'])
                            <p class="text-sm text-gray-600">
                                Vence: {{ $certificateStatus['expires_at']->format('d/m/Y') }}
                                @if($certificateStatus['days_to_expiry'] !== null && $certificateStatus['days_to_expiry'] > 0)
                                    ({{ $certificateStatus['days_to_expiry'] }} días restantes)
                                @endif
                            </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Formulario de Consulta --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">🔍 Consultar Estado en Aduana</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Consulte el estado de manifiestos enviados previamente a AFIP o DNA Paraguay.
                    </p>
                </div>

                <form action="{{ route('company.webservices.process-query') }}" method="POST" class="p-6 space-y-6">
                    @csrf

                    {{-- Tipo de Consulta --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            Tipo de Consulta *
                        </label>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input id="query_transaction" 
                                       name="query_type" 
                                       type="radio" 
                                       value="transaction_id"
                                       {{ old('query_type', 'transaction_id') === 'transaction_id' ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <label for="query_transaction" class="ml-2 block text-sm text-gray-900">
                                    Por ID de Transacción
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input id="query_reference" 
                                       name="query_type" 
                                       type="radio" 
                                       value="reference"
                                       {{ old('query_type') === 'reference' ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <label for="query_reference" class="ml-2 block text-sm text-gray-900">
                                    Por Referencia Externa
                                </label>
                            </div>
                        </div>
                        @error('query_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Configuración --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        {{-- Tipo de Webservice --}}
                        <div>
                            <label for="webservice_type" class="block text-sm font-medium text-gray-700">
                                Tipo de Webservice *
                            </label>
                            <select name="webservice_type" 
                                    id="webservice_type" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    required>
                                <option value="">Seleccionar tipo</option>
                                @foreach($availableTypes as $key => $name)
                                <option value="{{ $key }}" {{ old('webservice_type') === $key ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                                @endforeach
                            </select>
                            @error('webservice_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Valor de Consulta --}}
                        <div>
                            <label for="query_value" class="block text-sm font-medium text-gray-700">
                                <span id="query_label">ID de Transacción *</span>
                            </label>
                            <input type="text" 
                                   name="query_value" 
                                   id="query_value" 
                                   value="{{ old('query_value') }}"
                                   placeholder="Ingrese el ID o referencia"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   required>
                            @error('query_value')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p id="query_help" class="mt-1 text-xs text-gray-500">
                                Ejemplo: AR30123456789202507251234567
                            </p>
                        </div>

                    </div>

                    {{-- Botones --}}
                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                        <a href="{{ route('company.webservices.index') }}" 
                           class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-lg transition-colors">
                            Cancelar
                        </a>
                        
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                            🔍 Consultar Estado
                        </button>
                    </div>

                </form>
            </div>

            {{-- Información de Ayuda --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">💡 Información de Ayuda</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        
                        {{-- ID de Transacción --}}
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-medium text-blue-900 mb-2">📋 ID de Transacción</h4>
                            <p class="text-sm text-blue-800 mb-2">
                                Es el identificador único que se genera cuando envía un manifiesto a la aduana.
                            </p>
                            <div class="text-xs text-blue-700">
                                <p><strong>Formato:</strong> [País][Empresa][Timestamp][Random]</p>
                                <p><strong>Ejemplo:</strong> AR30123456789202507251234567</p>
                                <p><strong>Ubicación:</strong> Lo encuentra en el historial de transacciones</p>
                            </div>
                        </div>

                        {{-- Referencia Externa --}}
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-medium text-green-900 mb-2">🔗 Referencia Externa</h4>
                            <p class="text-sm text-green-800 mb-2">
                                Es una referencia que usted asigna al manifiesto para identificarlo fácilmente.
                            </p>
                            <div class="text-xs text-green-700">
                                <p><strong>Ejemplos:</strong> VIAJE-001, MAN-2025-001, REF-BUQUE-123</p>
                                <p><strong>Ubicación:</strong> Se configura al importar el manifiesto</p>
                            </div>
                        </div>

                        {{-- Estados Posibles --}}
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-2">📊 Estados Posibles</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
                                    <span><strong>Pendiente:</strong> En proceso de envío</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                    <span><strong>Exitoso:</strong> Aceptado por la aduana</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                    <span><strong>Error:</strong> Rechazado o falló</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="w-2 h-2 bg-gray-500 rounded-full"></span>
                                    <span><strong>Expirado:</strong> Tiempo límite excedido</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Webservices Disponibles --}}
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">🌐 Webservices Disponibles para Consulta</h3>
                </div>
                <div class="p-6">
                    @if(!empty($availableTypes))
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($availableTypes as $key => $name)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-900">{{ $name }}</h4>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                        Disponible
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">
                                    {{ match($key) {
                                        'anticipada' => 'Consultar estado de información anticipada enviada a AFIP',
                                        'micdta' => 'Consultar estado de manifiestos MIC/DTA en AFIP',
                                        'desconsolidados' => 'Consultar estado de títulos de desconsolidación',
                                        'transbordos' => 'Consultar estado de operaciones de transbordo',
                                        'paraguay' => 'Consultar estado de declaraciones en DNA Paraguay',
                                        default => 'Consultar estado de transacciones'
                                    } }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Tipo: {{ $key }}
                                </p>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-6">
                            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="text-gray-400">⚠️</span>
                            </div>
                            <p class="text-gray-600 text-sm">No hay webservices disponibles para consulta</p>
                            <p class="text-gray-500 text-xs mt-1">
                                Contacte al administrador para asignar roles a su empresa
                            </p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- JavaScript para mejorar la UX --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const queryTypeInputs = document.querySelectorAll('input[name="query_type"]');
            const queryLabel = document.getElementById('query_label');
            const queryHelp = document.getElementById('query_help');
            const queryInput = document.getElementById('query_value');

            function updateQueryFields() {
                const selectedType = document.querySelector('input[name="query_type"]:checked').value;
                
                if (selectedType === 'transaction_id') {
                    queryLabel.textContent = 'ID de Transacción *';
                    queryInput.placeholder = 'Ej: AR30123456789202507251234567';
                    queryHelp.textContent = 'Ingrese el ID de transacción completo generado por el sistema';
                } else {
                    queryLabel.textContent = 'Referencia Externa *';
                    queryInput.placeholder = 'Ej: VIAJE-001, MAN-2025-001';
                    queryHelp.textContent = 'Ingrese la referencia que asignó al manifiesto';
                }
            }

            // Actualizar campos al cambiar tipo de consulta
            queryTypeInputs.forEach(input => {
                input.addEventListener('change', updateQueryFields);
            });

            // Configuración inicial
            updateQueryFields();

            // Validación del formulario
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const queryValue = queryInput.value.trim();
                const selectedType = document.querySelector('input[name="query_type"]:checked').value;
                
                if (!queryValue) {
                    e.preventDefault();
                    alert('Por favor ingrese el valor de consulta');
                    queryInput.focus();
                    return;
                }

                // Validación básica para ID de transacción
                if (selectedType === 'transaction_id' && queryValue.length < 10) {
                    e.preventDefault();
                    alert('El ID de transacción parece muy corto. Verifique que sea correcto.');
                    queryInput.focus();
                    return;
                }

                // Confirmación antes de enviar
                const webserviceType = document.getElementById('webservice_type').value;
                const typeName = document.querySelector(`#webservice_type option[value="${webserviceType}"]`).textContent;
                
                if (!confirm(`¿Confirma que desea consultar el estado en ${typeName}?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</x-app-layout>