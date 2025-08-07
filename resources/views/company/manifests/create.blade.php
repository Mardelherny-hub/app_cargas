<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📋 Nuevo Manifiesto
            </h2>
            <nav class="text-sm">
                <a href="{{ route('company.manifests.index') }}" class="text-gray-500 hover:text-gray-700">
                    Manifiestos
                </a>
                <span class="text-gray-400 mx-2">/</span>
                <span class="text-gray-900">Crear</span>
            </nav>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Información introductoria -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Creación de Manifiesto Manual
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>Un manifiesto agrupa las cargas por viaje. Una vez creado, podrá agregar embarques individuales y generar los reportes necesarios para presentar a las autoridades aduaneras.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg">
                <!-- Header del formulario -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información del Viaje</h3>
                    <p class="mt-1 text-sm text-gray-600">Complete la información básica del viaje para crear el manifiesto.</p>
                </div>

                <!-- Formulario -->
                <form method="POST" action="{{ route('company.manifests.store') }}" class="p-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Número de Viaje -->
                        <div class="md:col-span-2">
                            <label for="voyage_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Número de Viaje <span class="text-red-500">*</span>
                            </label>
                            <input 
                                id="voyage_number" 
                                name="voyage_number" 
                                type="text" 
                                class="border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm block w-full" 
                                value="{{ old('voyage_number') }}"
                                placeholder="Ej: V2025-001, MAERSK-240801"
                                required 
                                autofocus 
                            />
                            @if($errors->has('voyage_number'))
                                <div class="text-sm text-red-600 mt-1">
                                    {{ $errors->first('voyage_number') }}
                                </div>
                            @endif
                            <p class="mt-1 text-xs text-gray-500">
                                Identificador único del viaje. No se puede modificar una vez creado.
                            </p>
                        </div>

                        <!-- Puerto de Origen -->
                        <div>
                            <label for="origin_port_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Puerto de Origen <span class="text-red-500">*</span>
                            </label>
                            <select 
                                id="origin_port_id" 
                                name="origin_port_id" 
                                class="border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm block w-full"
                                required
                            >
                                <option value="">Seleccione puerto de origen...</option>
                                @if(isset($ports) && $ports->count() > 0)
                                    @foreach($ports->groupBy('country.name') as $countryName => $countryPorts)
                                        <optgroup label="{{ $countryName }}">
                                            @foreach($countryPorts as $port)
                                                <option value="{{ $port->id }}" {{ old('origin_port_id') == $port->id ? 'selected' : '' }}>
                                                    {{ $port->name }} @if($port->code)({{ $port->code }})@endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                @else
                                    <option value="" disabled>No hay puertos disponibles</option>
                                @endif
                            </select>
                            @if($errors->has('origin_port_id'))
                                <div class="text-sm text-red-600 mt-1">
                                    {{ $errors->first('origin_port_id') }}
                                </div>
                            @endif
                        </div>

                        <!-- Puerto de Destino -->
                        <div>
                            <label for="destination_port_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Puerto de Destino <span class="text-red-500">*</span>
                            </label>
                            <select 
                                id="destination_port_id" 
                                name="destination_port_id" 
                                class="border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm block w-full"
                                required
                            >
                                <option value="">Seleccione puerto de destino...</option>
                                @if(isset($ports) && $ports->count() > 0)
                                    @foreach($ports->groupBy('country.name') as $countryName => $countryPorts)
                                        <optgroup label="{{ $countryName }}">
                                            @foreach($countryPorts as $port)
                                                <option value="{{ $port->id }}" {{ old('destination_port_id') == $port->id ? 'selected' : '' }}>
                                                    {{ $port->name }} @if($port->code)({{ $port->code }})@endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                @else
                                    <option value="" disabled>No hay puertos disponibles</option>
                                @endif
                            </select>
                            @if($errors->has('destination_port_id'))
                                <div class="text-sm text-red-600 mt-1">
                                    {{ $errors->first('destination_port_id') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Información adicional -->
                    <div class="mt-6 bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">¿Qué sucede después?</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li class="flex items-start">
                                <span class="text-green-500 mr-2">1.</span>
                                Se creará el viaje con estado "En Planificación"
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-500 mr-2">2.</span>
                                Podrá agregar embarques individuales al manifiesto
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-500 mr-2">3.</span>
                                Cuando esté completo, podrá exportar e enviar a aduana
                            </li>
                        </ul>
                    </div>

                    <!-- Botones de acción -->
                    <div class="mt-8 flex justify-between">
                        <a 
                            href="{{ route('company.manifests.index') }}" 
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Cancelar
                        </a>
                        
                        <button 
                            type="submit" 
                            class="inline-flex items-center px-6 py-2 bg-blue-600 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Crear Manifiesto
                        </button>
                    </div>
                </form>
            </div>

            <!-- Información de ayuda -->
            <div class="mt-6 bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-900 mb-2">💡 Consejos</h4>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Use un formato consistente para los números de viaje (ej: V2025-001)</li>
                    <li>• Verifique que los puertos seleccionados sean correctos antes de crear</li>
                    <li>• También puede <a href="{{ route('company.manifests.import.index') }}" class="text-blue-600 hover:text-blue-800">importar manifiestos desde archivos</a></li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>