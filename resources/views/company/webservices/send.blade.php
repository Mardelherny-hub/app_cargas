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
                        <!-- Formulario para Cargas -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="trip_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Seleccionar Viaje *
                                </label>
                                <select name="trip_id" id="trip_id" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecciona un viaje</option>
                                    @forelse($data['trips'] ?? [] as $trip)
                                        <option value="{{ $trip['id'] }}">{{ $trip['name'] }}</option>
                                    @empty
                                        <option value="" disabled>No hay viajes disponibles</option>
                                    @endforelse
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Solo se muestran viajes pendientes de envío</p>
                            </div>

                            <div>
                                <label for="environment" class="block text-sm font-medium text-gray-700 mb-2">
                                    Ambiente
                                </label>
                                <select name="environment" id="environment"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="testing" {{ $company->ws_environment === 'testing' ? 'selected' : '' }}>Testing</option>
                                    <option value="production" {{ $company->ws_environment === 'production' ? 'selected' : '' }}>Producción</option>
                                </select>
                            </div>
                        </div>

                        @if($webserviceType === 'micdta')
                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Opciones MIC/DTA
                                </label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="include_containers" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Incluir información de contenedores</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="validate_weights" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Validar pesos y volúmenes</span>
                                    </label>
                                </div>
                            </div>
                        @endif

                    @elseif($webserviceType === 'desconsolidados')
                        <!-- Formulario para Desconsolidados -->
                        <div class="space-y-6">
                            <div>
                                <label for="parent_shipment" class="block text-sm font-medium text-gray-700 mb-2">
                                    Título Madre *
                                </label>
                                <select name="parent_shipment" id="parent_shipment" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="">Selecciona título madre</option>
                                    @forelse($data['shipments'] ?? [] as $shipment)
                                        <option value="{{ $shipment['id'] }}">{{ $shipment['number'] }}</option>
                                    @empty
                                        <option value="" disabled>No hay títulos disponibles</option>
                                    @endforelse
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Operación
                                </label>
                                <div class="grid grid-cols-3 gap-4">
                                    <label class="flex items-center">
                                        <input type="radio" name="operation_type" value="register" checked class="text-purple-600 focus:ring-purple-500">
                                        <span class="ml-2 text-sm">Registrar</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="operation_type" value="modify" class="text-purple-600 focus:ring-purple-500">
                                        <span class="ml-2 text-sm">Modificar</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="operation_type" value="cancel" class="text-purple-600 focus:ring-purple-500">
                                        <span class="ml-2 text-sm">Cancelar</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                    @elseif($webserviceType === 'transbordos')
                        <!-- Formulario para Transbordos -->
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="barge_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Barcaza *
                                    </label>
                                    <select name="barge_id" id="barge_id" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                        <option value="">Selecciona barcaza</option>
                                        @forelse($data['barges'] ?? [] as $barge)
                                            <option value="{{ $barge['id'] }}">{{ $barge['name'] }}</option>
                                        @empty
                                            <option value="" disabled>No hay barcazas disponibles</option>
                                        @endforelse
                                    </select>
                                </div>

                                <div>
                                    <label for="route" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ruta
                                    </label>
                                    <input type="text" name="route" id="route" placeholder="ARBUE-PYTVT"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Transbordo
                                </label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="flex items-center">
                                        <input type="radio" name="transshipment_type" value="position_update" checked class="text-orange-600 focus:ring-orange-500">
                                        <span class="ml-2 text-sm">Actualización de Posición</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="transshipment_type" value="cargo_transfer" class="text-orange-600 focus:ring-orange-500">
                                        <span class="ml-2 text-sm">Transferencia de Carga</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif

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