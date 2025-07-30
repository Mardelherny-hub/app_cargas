<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Envío de Manifiestos') }}
            </h2>
            <a href="{{ route('company.webservices.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Volver al Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Selector de Tipo de Webservice -->
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Seleccionar Tipo de Webservice</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        @if(in_array('anticipada', $availableTypes))
                            <a href="{{ route('company.webservices.send', ['type' => 'anticipada']) }}" 
                               class="block p-4 border rounded-lg hover:border-blue-500 transition-colors {{ $webserviceType === 'anticipada' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🇦🇷</span>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Info. Anticipada</h4>
                                        <p class="text-sm text-gray-500">Registro de viajes</p>
                                    </div>
                                </div>
                            </a>
                        @endif

                        @if(in_array('micdta', $availableTypes))
                            <a href="{{ route('company.webservices.send', ['type' => 'micdta']) }}" 
                               class="block p-4 border rounded-lg hover:border-green-500 transition-colors {{ $webserviceType === 'micdta' ? 'border-green-500 bg-green-50' : 'border-gray-200' }}">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <div>
                                        <h4 class="font-medium text-gray-900">MIC/DTA</h4>
                                        <p class="text-sm text-gray-500">Registro de envíos</p>
                                    </div>
                                </div>
                            </a>
                        @endif

                        @if(in_array('desconsolidados', $availableTypes))
                            <a href="{{ route('company.webservices.send', ['type' => 'desconsolidados']) }}" 
                               class="block p-4 border rounded-lg hover:border-purple-500 transition-colors {{ $webserviceType === 'desconsolidados' ? 'border-purple-500 bg-purple-50' : 'border-gray-200' }}">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Desconsolidados</h4>
                                        <p class="text-sm text-gray-500">Títulos madre/hijo</p>
                                    </div>
                                </div>
                            </a>
                        @endif

                        @if(in_array('transbordos', $availableTypes))
                            <a href="{{ route('company.webservices.send', ['type' => 'transbordos']) }}" 
                               class="block p-4 border rounded-lg hover:border-orange-500 transition-colors {{ $webserviceType === 'transbordos' ? 'border-orange-500 bg-orange-50' : 'border-gray-200' }}">
                                <div class="flex items-center">
                                    <svg class="w-8 h-8 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <div>
                                        <h4 class="font-medium text-gray-900">Transbordos</h4>
                                        <p class="text-sm text-gray-500">Barcazas y transferencias</p>
                                    </div>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Formulario de Envío -->
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        @switch($webserviceType)
                            @case('anticipada')
                                Envío de Información Anticipada 🇦🇷
                                @break
                            @case('micdta')
                                Registro MIC/DTA 🇦🇷
                                @break
                            @case('desconsolidados')
                                Gestión de Desconsolidados
                                @break
                            @case('transbordos')
                                Registro de Transbordos
                                @break
                        @endswitch
                    </h3>
                </div>

                <form action="{{ route('company.webservices.process-send') }}" method="POST" class="p-6">
                    @csrf
                    <input type="hidden" name="webservice_type" value="{{ $webserviceType }}">

@if($webserviceType === 'anticipada' || $webserviceType === 'micdta')
    <!-- Formulario para Cargas - CORREGIDO -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="trip_id" class="block text-sm font-medium text-gray-700 mb-2">
                Seleccionar Viaje *
            </label>
            <select name="trip_id" id="trip_id" required 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Selecciona un viaje</option>
                @forelse($data['voyages'] ?? [] as $voyage)
                    <option value="{{ $voyage['id'] }}" 
                            data-voyage-number="{{ $voyage['voyage_number'] ?? '' }}"
                            data-vessel="{{ $voyage['vessel_name'] ?? '' }}"
                            data-route="{{ $voyage['route'] ?? '' }}"
                            data-date="{{ $voyage['departure_date'] ?? '' }}"
                            data-captain="{{ $voyage['captain_name'] ?? '' }}"
                            data-status="{{ $voyage['status'] ?? '' }}">
                        {{ $voyage['display_text'] ?? $voyage['number'] ?? 'Viaje sin nombre' }}
                    </option>
                @empty
                    <option value="" disabled>No hay viajes disponibles para webservices</option>
                @endforelse
            </select>
            
            {{-- Información adicional del viaje seleccionado --}}
            <div id="voyage-info" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md" style="display: none;">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">📋 Información del Viaje</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-gray-700">
                    <div><strong>Viaje:</strong> <span id="info-voyage" class="text-blue-600"></span></div>
                    <div><strong>Estado:</strong> <span id="info-status" class="text-blue-600"></span></div>
                    <div><strong>Embarcación:</strong> <span id="info-vessel" class="text-blue-600"></span></div>
                    <div><strong>Capitán:</strong> <span id="info-captain" class="text-blue-600"></span></div>
                    <div class="sm:col-span-2"><strong>Ruta:</strong> <span id="info-route" class="text-blue-600"></span></div>
                    <div class="sm:col-span-2"><strong>Fecha de Salida:</strong> <span id="info-date" class="text-blue-600"></span></div>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Operación
            </label>
            <div class="grid grid-cols-3 gap-4">
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="register" checked 
                           class="text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm">Registrar</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="modify" 
                           class="text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm">Modificar</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="cancel" 
                           class="text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm">Cancelar</span>
                </label>
            </div>

            {{-- Opciones adicionales para Argentina --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Configuración de Envío
                </label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="send_immediately" value="1" 
                               class="text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm">Enviar inmediatamente</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="include_containers" value="1" checked
                               class="text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm">Incluir contenedores</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="include_cargo" value="1" checked
                               class="text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm">Incluir carga</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- Campos de configuración avanzada --}}
    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
        <h4 class="text-md font-medium text-gray-900 mb-4">⚙️ Configuración Avanzada</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="environment" class="block text-sm font-medium text-gray-700 mb-1">
                    Ambiente
                </label>
                <select name="environment" id="environment" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="testing">Testing</option>
                    <option value="production">Producción</option>
                </select>
            </div>
            
            <div>
                <label for="country" class="block text-sm font-medium text-gray-700 mb-1">
                    País Destino
                </label>
                <select name="country" id="country" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="AR">Argentina 🇦🇷</option>
                    <option value="PY">Paraguay 🇵🇾</option>
                </select>
            </div>

            <div>
                <label for="data_source" class="block text-sm font-medium text-gray-700 mb-1">
                    Fuente de Datos
                </label>
                <select name="data_source" id="data_source" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="voyage_id">Datos del Viaje</option>
                    <option value="manual">Datos Manuales</option>
                </select>
            </div>
        </div>
    </div>

@elseif($webserviceType === 'desconsolidados')
    <!-- Formulario para Desconsolidados - CORREGIDO -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="shipment_id" class="block text-sm font-medium text-gray-700 mb-2">
                Seleccionar Título *
            </label>
            <select name="shipment_id" id="shipment_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="">Selecciona un título</option>
                @forelse($data['shipments'] ?? [] as $shipment)
                    <option value="{{ $shipment['id'] }}"
                            data-type="{{ $shipment['type'] ?? '' }}"
                            data-client="{{ $shipment['client_name'] ?? '' }}">
                        {{ $shipment['display_text'] ?? $shipment['number'] ?? 'Título sin nombre' }}
                    </option>
                @empty
                    <option value="" disabled>No hay títulos disponibles</option>
                @endforelse
            </select>

            {{-- Información del título seleccionado --}}
            <div id="shipment-info" class="mt-3 p-3 bg-purple-50 border border-purple-200 rounded-md" style="display: none;">
                <h4 class="text-sm font-semibold text-purple-800 mb-2">📋 Información del Título</h4>
                <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                    <div><strong>Tipo:</strong> <span id="info-shipment-type" class="text-purple-600"></span></div>
                    <div><strong>Cliente:</strong> <span id="info-client" class="text-purple-600"></span></div>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Operación
            </label>
            <div class="grid grid-cols-3 gap-4">
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="register" checked 
                           class="text-purple-600 focus:ring-purple-500">
                    <span class="ml-2 text-sm">Registrar</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="modify" 
                           class="text-purple-600 focus:ring-purple-500">
                    <span class="ml-2 text-sm">Modificar</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="cancel" 
                           class="text-purple-600 focus:ring-purple-500">
                    <span class="ml-2 text-sm">Cancelar</span>
                </label>
            </div>
        </div>
    </div>

@elseif($webserviceType === 'transbordos')
    <!-- Formulario para Transbordos - CORREGIDO -->
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="barge_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Barcaza Origen *
                </label>
                <select name="barge_id" id="barge_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">Selecciona barcaza origen</option>
                    @forelse($data['barges'] ?? [] as $barge)
                        <option value="{{ $barge['id'] }}"
                                data-name="{{ $barge['name'] ?? '' }}"
                                data-type="{{ $barge['type'] ?? '' }}">
                            {{ $barge['display_text'] ?? $barge['number'] ?? 'Barcaza sin nombre' }}
                        </option>
                    @empty
                        <option value="" disabled>No hay barcazas disponibles</option>
                    @endforelse
                </select>
            </div>

            <div>
                <label for="transfer_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Barcaza Destino
                </label>
                <select name="transfer_id" id="transfer_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">Selecciona barcaza destino</option>
                    @forelse($data['transfers'] ?? [] as $transfer)
                        <option value="{{ $transfer['id'] }}"
                                data-from="{{ $transfer['from_vessel'] ?? '' }}"
                                data-to="{{ $transfer['to_vessel'] ?? '' }}">
                            {{ $transfer['display_text'] ?? $transfer['number'] ?? 'Transbordo sin nombre' }}
                        </option>
                    @empty
                        <option value="" disabled>No hay opciones de transbordo</option>
                    @endforelse
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Operación
            </label>
            <div class="grid grid-cols-3 gap-4">
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="register" checked 
                           class="text-orange-600 focus:ring-orange-500">
                    <span class="ml-2 text-sm">Registrar</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="modify" 
                           class="text-orange-600 focus:ring-orange-500">
                    <span class="ml-2 text-sm">Modificar</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="operation_type" value="cancel" 
                           class="text-orange-600 focus:ring-orange-500">
                    <span class="ml-2 text-sm">Cancelar</span>
                </label>
            </div>
        </div>
    </div>
@endif

{{-- Botones de acción --}}
<div class="flex items-center justify-between pt-6 border-t border-gray-200">
    <a href="{{ route('company.webservices.index') }}" 
       class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors">
        ← Volver al Dashboard
    </a>
    
    <div class="flex space-x-3">
        <button type="button" 
                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
            Vista Previa
        </button>
        <button type="submit" 
                class="px-6 py-2 text-sm text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
            @switch($webserviceType)
                @case('anticipada')
                    🇦🇷 Enviar Info. Anticipada
                    @break
                @case('micdta')
                    🇦🇷 Registrar MIC/DTA
                    @break
                @case('desconsolidados')
                    📋 Gestionar Desconsolidados
                    @break
                @case('transbordos')
                    ⚡ Registrar Transbordos
                    @break
                @default
                    Enviar a Webservice
            @endswitch
        </button>
    </div>
</div>

                    <!-- Observaciones -->
                    <div class="mt-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                            Observaciones
                        </label>
                        <textarea name="notes" id="notes" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Observaciones adicionales (opcional)"></textarea>
                    </div>

                    <!-- Botones -->
                    <div class="mt-8 flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Ambiente:</span> {{ ucfirst($company->ws_environment ?? 'testing') }}
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" onclick="history.back()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                🚀 Enviar Manifiesto
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funcionalidad para el select de viajes
    const tripSelect = document.getElementById('trip_id');
    const tripInfo = document.getElementById('voyage-info');
    
    if (tripSelect && tripInfo) {
        tripSelect.addEventListener('change', function() {
            if (this.value && this.value !== 'example_1' && this.value !== 'example_2') {
                const selectedOption = this.options[this.selectedIndex];
                
                // Actualizar información del viaje
                document.getElementById('info-voyage').textContent = 
                    selectedOption.dataset.voyageNumber || 'N/A';
                document.getElementById('info-vessel').textContent = 
                    selectedOption.dataset.vessel || 'N/A';
                document.getElementById('info-route').textContent = 
                    selectedOption.dataset.route || 'N/A';
                document.getElementById('info-date').textContent = 
                    selectedOption.dataset.date || 'N/A';
                document.getElementById('info-captain').textContent = 
                    selectedOption.dataset.captain || 'N/A';
                document.getElementById('info-status').textContent = 
                    selectedOption.dataset.status || 'N/A';
                
                tripInfo.style.display = 'block';
            } else {
                tripInfo.style.display = 'none';
            }
        });
    }

    // Funcionalidad para el select de shipments
    const shipmentSelect = document.getElementById('shipment_id');
    const shipmentInfo = document.getElementById('shipment-info');
    
    if (shipmentSelect && shipmentInfo) {
        shipmentSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                
                document.getElementById('info-shipment-type').textContent = 
                    selectedOption.dataset.type || 'N/A';
                document.getElementById('info-client').textContent = 
                    selectedOption.dataset.client || 'N/A';
                
                shipmentInfo.style.display = 'block';
            } else {
                shipmentInfo.style.display = 'none';
            }
        });
    }

    // Validaciones del formulario
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const tripId = document.getElementById('trip_id');
            const shipmentId = document.getElementById('shipment_id');
            const bargeId = document.getElementById('barge_id');
            
            // Validar según el tipo de webservice
            const webserviceType = '{{ $webserviceType }}';
            
            if ((webserviceType === 'anticipada' || webserviceType === 'micdta') && 
                tripId && !tripId.value) {
                e.preventDefault();
                alert('Por favor selecciona un viaje.');
                tripId.focus();
                return;
            }
            
            if (webserviceType === 'desconsolidados' && shipmentId && !shipmentId.value) {
                e.preventDefault();
                alert('Por favor selecciona un título.');
                shipmentId.focus();
                return;
            }
            
            if (webserviceType === 'transbordos' && bargeId && !bargeId.value) {
                e.preventDefault();
                alert('Por favor selecciona una barcaza.');
                bargeId.focus();
                return;
            }
        });
    }
});
</script>
@endpush