<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Nueva Embarcación') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Registre una nueva embarcación en el sistema
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Volver a Lista -->
                <a href="{{ route('company.vessels.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver a Lista
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Formulario de Creación -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    
                    <form action="{{ route('company.vessels.store') }}" method="POST" class="space-y-6">
                        @csrf

                        <!-- Información Básica -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información Básica</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- Nombre de la Embarcación -->
                                <div class="md:col-span-2">
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre de la Embarcación *
                                    </label>
                                    <input type="text" 
                                           name="name" 
                                           id="name"
                                           value="{{ old('name') }}"
                                           required
                                           maxlength="255"
                                           placeholder="Ej: MV GUARANÍ"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('name') border-red-300 @enderror">
                                    @error('name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Número IMO -->
                                <div>
                                    <label for="imo_number" class="block text-sm font-medium text-gray-700 mb-1">
                                        Número IMO
                                        <span class="text-gray-500 text-xs">(Opcional)</span>
                                    </label>
                                    <input type="text" 
                                           name="imo_number" 
                                           id="imo_number"
                                           value="{{ old('imo_number') }}"
                                           maxlength="20"
                                           placeholder="Ej: IMO1234567"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('imo_number') border-red-300 @enderror">
                                    @error('imo_number')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">El número IMO debe ser único en el sistema</p>
                                </div>

                                <!-- Estado -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                        Estado *
                                    </label>
                                    <select name="status" id="status" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('status') border-red-300 @enderror">
                                        <option value="">Seleccione un estado</option>
                                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Activa</option>
                                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactiva</option>
                                        <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Mantenimiento</option>
                                        <option value="dry_dock" {{ old('status') === 'dry_dock' ? 'selected' : '' }}>Dique Seco</option>
                                    </select>
                                    @error('status')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Clasificación -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Clasificación</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- Tipo de Embarcación -->
                                <div>
                                    <label for="vessel_type_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        Tipo de Embarcación *
                                    </label>
                                    <select name="vessel_type_id" id="vessel_type_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('vessel_type_id') border-red-300 @enderror">
                                        <option value="">Seleccione un tipo</option>
                                        @foreach($vesselTypes as $id => $name)
                                            <option value="{{ $id }}" {{ old('vessel_type_id') == $id ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vessel_type_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Propietario -->
                                <div>
                                    <label for="vessel_owner_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        Propietario *
                                    </label>
                                    <select name="vessel_owner_id" id="vessel_owner_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('vessel_owner_id') border-red-300 @enderror">
                                        <option value="">Seleccione un propietario</option>
                                        @foreach($vesselOwners as $id => $name)
                                            <option value="{{ $id }}" {{ old('vessel_owner_id') == $id ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vessel_owner_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    @if($vesselOwners->isEmpty())
                                        <p class="mt-1 text-xs text-red-600">
                                            No hay propietarios disponibles. 
                                            <a href="{{ route('company.vessel-owners.create') }}" class="text-blue-600 hover:text-blue-800 underline">
                                                Crear propietario
                                            </a>
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Especificaciones Técnicas -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Especificaciones Técnicas</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                
                                <!-- Longitud -->
                                <div>
                                    <label for="length_meters" class="block text-sm font-medium text-gray-700 mb-1">
                                        Longitud (metros)
                                        <span class="text-gray-500 text-xs">(Opcional)</span>
                                    </label>
                                    <input type="number" 
                                           name="length_meters" 
                                           id="length_meters"
                                           value="{{ old('length_meters') }}"
                                           step="0.01"
                                           min="0"
                                           max="999.99"
                                           placeholder="Ej: 45.5"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('length_meters') border-red-300 @enderror">
                                    @error('length_meters')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Tonelaje Bruto -->
                                <div>
                                    <label for="gross_tonnage" class="block text-sm font-medium text-gray-700 mb-1">
                                        Tonelaje Bruto (TRB)
                                        <span class="text-gray-500 text-xs">(Opcional)</span>
                                    </label>
                                    <input type="number" 
                                           name="gross_tonnage" 
                                           id="gross_tonnage"
                                           value="{{ old('gross_tonnage') }}"
                                           step="0.01"
                                           min="0"
                                           max="999999.99"
                                           placeholder="Ej: 1500.00"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('gross_tonnage') border-red-300 @enderror">
                                    @error('gross_tonnage')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Capacidad de Contenedores -->
                                <div>
                                    <label for="container_capacity" class="block text-sm font-medium text-gray-700 mb-1">
                                        Capacidad de Contenedores
                                        <span class="text-gray-500 text-xs">(Opcional)</span>
                                    </label>
                                    <input type="number" 
                                           name="container_capacity" 
                                           id="container_capacity"
                                           value="{{ old('container_capacity') }}"
                                           min="0"
                                           max="99999"
                                           placeholder="Ej: 120"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('container_capacity') border-red-300 @enderror">
                                    @error('container_capacity')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Número máximo de contenedores TEU</p>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                            <div class="text-sm text-gray-500">
                                <span class="text-red-500">*</span> Campos obligatorios
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                <!-- Cancelar -->
                                <a href="{{ route('company.vessels.index') }}" 
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </a>
                                
                                <!-- Guardar -->
                                <button type="submit" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Crear Embarcación
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Información de Ayuda -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Información importante</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc space-y-1 pl-5">
                                <li>El nombre de la embarcación debe ser único e identificativo</li>
                                <li>El número IMO (International Maritime Organization) es opcional pero recomendado para embarcaciones marítimas</li>
                                <li>Solo podrá seleccionar propietarios que pertenezcan a su empresa</li>
                                <li>Las especificaciones técnicas son opcionales pero útiles para informes y estadísticas</li>
                                <li>Puede modificar estos datos posteriormente desde la sección de edición</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>