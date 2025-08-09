<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Editar Embarcación') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Modificar información de: {{ $vessel->name }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Ver Embarcación -->
                <a href="{{ route('company.vessels.show', $vessel) }}" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver Detalles
                </a>
                
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
            
            <!-- Estado Actual -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $vessel->name }}</h3>
                            <p class="text-sm text-gray-600">
                                {{ $vessel->vesselType->name ?? 'Tipo no especificado' }} • 
                                Registrada: {{ $vessel->created_at->format('d/m/Y') }}
                            </p>
                        </div>
                        <div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                @switch($vessel->status)
                                    @case('active') bg-green-100 text-green-800 @break
                                    @case('inactive') bg-gray-100 text-gray-800 @break
                                    @case('maintenance') bg-yellow-100 text-yellow-800 @break
                                    @case('dry_dock') bg-red-100 text-red-800 @break
                                    @default bg-gray-100 text-gray-800
                                @endswitch">
                                @switch($vessel->status)
                                    @case('active') Activa @break
                                    @case('inactive') Inactiva @break
                                    @case('maintenance') Mantenimiento @break
                                    @case('dry_dock') Dique Seco @break
                                    @default {{ ucfirst($vessel->status) }}
                                @endswitch
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Edición -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    
                    <form action="{{ route('company.vessels.update', $vessel) }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')

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
                                           value="{{ old('name', $vessel->name) }}"
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
                                           value="{{ old('imo_number', $vessel->imo_number) }}"
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
                                        <option value="active" {{ old('status', $vessel->status) === 'active' ? 'selected' : '' }}>Activa</option>
                                        <option value="inactive" {{ old('status', $vessel->status) === 'inactive' ? 'selected' : '' }}>Inactiva</option>
                                        <option value="maintenance" {{ old('status', $vessel->status) === 'maintenance' ? 'selected' : '' }}>Mantenimiento</option>
                                        <option value="dry_dock" {{ old('status', $vessel->status) === 'dry_dock' ? 'selected' : '' }}>Dique Seco</option>
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
                                            <option value="{{ $id }}" {{ old('vessel_type_id', $vessel->vessel_type_id) == $id ? 'selected' : '' }}>
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
                                    <label for="owner_id" class="block text-sm font-medium text-gray-700 mb-1">
                                        Propietario *
                                    </label>
                                    <select name="owner_id" id="owner_id" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('owner_id') border-red-300 @enderror">
                                        <option value="">Seleccione un propietario</option>
                                        @foreach($vesselOwners as $id => $name)
                                            <option value="{{ $id }}" {{ old('owner_id', $vessel->owner_id) == $id ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('owner_id')
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

                            <!-- Información del Propietario Actual -->
                            @if($vessel->vesselOwner)
                                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-blue-800">Propietario Actual</h4>
                                            <div class="mt-1 text-sm text-blue-700">
                                                <strong>{{ $vessel->vesselOwner->legal_name }}</strong><br>
                                                CUIT/RUC: {{ $vessel->vesselOwner->tax_id }}<br>
                                                Tipo: {{ $vessel->vesselOwner->transportista_type === 'O' ? 'Operador' : 'Representante' }}
                                                @if($vessel->vesselOwner->status !== 'active')
                                                    <br><span class="text-red-600">⚠️ Estado: {{ ucfirst($vessel->vesselOwner->status) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
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
                                           value="{{ old('length_meters', $vessel->length_meters) }}"
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
                                           value="{{ old('gross_tonnage', $vessel->gross_tonnage) }}"
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
                                           value="{{ old('container_capacity', $vessel->container_capacity) }}"
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

                            <!-- Valores Actuales -->
                            <div class="mt-4 p-3 bg-gray-100 border border-gray-200 rounded-md">
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Valores Actuales</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                                    <div>
                                        <span class="font-medium">Longitud:</span> 
                                        {{ $vessel->length_meters ? number_format($vessel->length_meters, 1) . ' m' : 'No especificada' }}
                                    </div>
                                    <div>
                                        <span class="font-medium">Tonelaje:</span> 
                                        {{ $vessel->gross_tonnage ? number_format($vessel->gross_tonnage) . ' t' : 'No especificado' }}
                                    </div>
                                    <div>
                                        <span class="font-medium">Contenedores:</span> 
                                        {{ $vessel->container_capacity ? number_format($vessel->container_capacity) . ' TEU' : 'No especificado' }}
                                    </div>
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
                                <a href="{{ route('company.vessels.show', $vessel) }}" 
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-md text-sm font-medium">
                                    Cancelar
                                </a>
                                
                                <!-- Guardar Cambios -->
                                <button type="submit" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Guardar Cambios
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Información de Ayuda -->
            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Consideraciones importantes</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc space-y-1 pl-5">
                                <li>Al cambiar el propietario, verifique que pertenezca a su empresa</li>
                                <li>El cambio de estado puede afectar la disponibilidad de la embarcación para nuevos viajes</li>
                                <li>El número IMO debe seguir el formato estándar internacional (ej: IMO1234567)</li>
                                <li>Los cambios se registrarán en el historial de auditoría del sistema</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>