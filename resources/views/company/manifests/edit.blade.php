<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Editar Manifiesto: {{ $voyage->voyage_number }}
            </h2>
            <div class="text-sm text-gray-500">
                Estado: <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full">{{ ucfirst($voyage->status) }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información del Manifiesto</h3>
                    <p class="text-sm text-gray-500 mt-1">Solo puede editar manifiestos en estado de planificación</p>
                </div>

                <form method="POST" action="{{ route('company.manifests.update', $voyage->id) }}" class="p-6">
                    @csrf
                    @method('PUT')

                    <!-- Información Básica -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-800 mb-4">Información Básica</h4>
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Número de Viaje (readonly) -->
                            <div>
                                <label for="voyage_number" class="block text-sm font-medium text-gray-700">
                                    Número de Viaje
                                </label>
                                <input type="text" 
                                       id="voyage_number" 
                                       name="voyage_number" 
                                       value="{{ $voyage->voyage_number }}" 
                                       readonly
                                       class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">El número de viaje no se puede modificar</p>
                            </div>
                        </div>
                    </div>

                    <!-- Origen -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-800 mb-4">Origen</h4>
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="origin_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Origen <span class="text-red-500">*</span>
                                </label>
                                <select name="origin_port_id" 
                                        id="origin_port_id" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('origin_port_id') border-red-300 @enderror">
                                    <option value="">Seleccione puerto de origen</option>
                                    @foreach($ports as $port)
                                        <option value="{{ $port->id }}" 
                                                {{ old('origin_port_id', $voyage->origin_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('origin_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Destino -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-800 mb-4">Destino</h4>
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="destination_port_id" class="block text-sm font-medium text-gray-700">
                                    Puerto de Destino <span class="text-red-500">*</span>
                                </label>
                                <select name="destination_port_id" 
                                        id="destination_port_id" 
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('destination_port_id') border-red-300 @enderror">
                                    <option value="">Seleccione puerto de destino</option>
                                    @foreach($ports as $port)
                                        <option value="{{ $port->id }}" 
                                                {{ old('destination_port_id', $voyage->destination_port_id) == $port->id ? 'selected' : '' }}>
                                            {{ $port->name }} ({{ $port->code }}) - {{ $port->country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('destination_port_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Estado Actual -->
                    <div class="mb-8">
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Información</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>• Solo se pueden editar puertos en manifiestos en estado de planificación</p>
                                        <p>• Los cambios afectarán todos los embarques asociados</p>
                                        <p>• Empresa: {{ $company->legal_name }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                        <a href="{{ route('company.manifests.show', $voyage->id) }}" 
                           class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancelar
                        </a>
                        
                        <div class="flex space-x-3">
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Actualizar Manifiesto
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const originSelect = document.getElementById('origin_port_id');
            const destinationSelect = document.getElementById('destination_port_id');

            form.addEventListener('submit', function(e) {
                // Validar que el puerto de origen sea diferente al destino
                if (originSelect.value && destinationSelect.value && originSelect.value === destinationSelect.value) {
                    e.preventDefault();
                    alert('El puerto de origen debe ser diferente al puerto de destino.');
                    return false;
                }
            });
        });
    </script>
</x-app-layout>