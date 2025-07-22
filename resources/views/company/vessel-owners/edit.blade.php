<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Editar Propietario de Embarcación') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $vesselOwner->commercial_name ?? $vesselOwner->legal_name }} • {{ $vesselOwner->tax_id }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Ver Propietario -->
                <a href="{{ route('company.vessel-owners.show', $vesselOwner) }}" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver
                </a>
                <!-- Volver a Lista -->
                <a href="{{ route('company.vessel-owners.index') }}" 
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
            
            <!-- Formulario -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('company.vessel-owners.update', $vesselOwner) }}">
                        @csrf
                        @method('PUT')

                        <!-- Información Básica -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información Básica</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- CUIT/RUC -->
                                <div>
                                    <label for="tax_id" class="block text-sm font-medium text-gray-700">
                                        CUIT/RUC <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="tax_id" 
                                           id="tax_id" 
                                           value="{{ old('tax_id', $vesselOwner->tax_id) }}"
                                           maxlength="15"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('tax_id') border-red-300 @enderror"
                                           placeholder="Ej: 30707654321"
                                           required>
                                    @error('tax_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- País -->
                                <div>
                                    <label for="country_id" class="block text-sm font-medium text-gray-700">
                                        País <span class="text-red-500">*</span>
                                    </label>
                                    <select name="country_id" 
                                            id="country_id" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('country_id') border-red-300 @enderror"
                                            required>
                                        <option value="">Seleccione un país</option>
                                        @foreach($countries as $id => $name)
                                            <option value="{{ $id }}" {{ (old('country_id', $vesselOwner->country_id) == $id) ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('country_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Razón Social -->
                                <div class="md:col-span-2">
                                    <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                        Razón Social <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="legal_name" 
                                           id="legal_name" 
                                           value="{{ old('legal_name', $vesselOwner->legal_name) }}"
                                           maxlength="200"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('legal_name') border-red-300 @enderror"
                                           placeholder="Ej: Naviera Río de la Plata S.A."
                                           required>
                                    @error('legal_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Nombre Comercial -->
                                <div class="md:col-span-2">
                                    <label for="commercial_name" class="block text-sm font-medium text-gray-700">
                                        Nombre Comercial
                                    </label>
                                    <input type="text" 
                                           name="commercial_name" 
                                           id="commercial_name" 
                                           value="{{ old('commercial_name', $vesselOwner->commercial_name) }}"
                                           maxlength="200"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('commercial_name') border-red-300 @enderror"
                                           placeholder="Ej: Naviera RDP">
                                    @error('commercial_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Tipo Transportista -->
                                <div>
                                    <label for="transportista_type" class="block text-sm font-medium text-gray-700">
                                        Tipo Transportista <span class="text-red-500">*</span>
                                    </label>
                                    <select name="transportista_type" 
                                            id="transportista_type" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('transportista_type') border-red-300 @enderror"
                                            required>
                                        <option value="">Seleccione tipo</option>
                                        <option value="O" {{ (old('transportista_type', $vesselOwner->transportista_type) == 'O') ? 'selected' : '' }}>O - Operador</option>
                                        <option value="R" {{ (old('transportista_type', $vesselOwner->transportista_type) == 'R') ? 'selected' : '' }}>R - Representante</option>
                                    </select>
                                    @error('transportista_type')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Requerido para webservices aduaneros</p>
                                </div>

                                <!-- Webservices -->
                                <div class="flex items-center">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" 
                                               name="webservice_authorized" 
                                               id="webservice_authorized" 
                                               value="1"
                                               {{ old('webservice_authorized', $vesselOwner->webservice_authorized) ? 'checked' : '' }}
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3">
                                        <label for="webservice_authorized" class="text-sm font-medium text-gray-700">
                                            Autorizado para Webservices
                                        </label>
                                        <p class="text-xs text-gray-500">Permitir uso en webservices aduaneros</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Datos de Contacto -->
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Datos de Contacto</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">
                                        Email
                                    </label>
                                    <input type="email" 
                                           name="email" 
                                           id="email" 
                                           value="{{ old('email', $vesselOwner->email) }}"
                                           maxlength="100"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-300 @enderror"
                                           placeholder="contacto@empresa.com">
                                    @error('email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Teléfono -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">
                                        Teléfono
                                    </label>
                                    <input type="text" 
                                           name="phone" 
                                           id="phone" 
                                           value="{{ old('phone', $vesselOwner->phone) }}"
                                           maxlength="50"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('phone') border-red-300 @enderror"
                                           placeholder="+54 11 4567-8900">
                                    @error('phone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Dirección -->
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700">
                                        Dirección
                                    </label>
                                    <textarea name="address" 
                                              id="address" 
                                              rows="2"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('address') border-red-300 @enderror"
                                              placeholder="Dirección completa">{{ old('address', $vesselOwner->address) }}</textarea>
                                    @error('address')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Ciudad -->
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700">
                                        Ciudad
                                    </label>
                                    <input type="text" 
                                           name="city" 
                                           id="city" 
                                           value="{{ old('city', $vesselOwner->city) }}"
                                           maxlength="100"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('city') border-red-300 @enderror"
                                           placeholder="Buenos Aires">
                                    @error('city')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Código Postal -->
                                <div>
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700">
                                        Código Postal
                                    </label>
                                    <input type="text" 
                                           name="postal_code" 
                                           id="postal_code" 
                                           value="{{ old('postal_code', $vesselOwner->postal_code) }}"
                                           maxlength="20"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('postal_code') border-red-300 @enderror"
                                           placeholder="C1106">
                                    @error('postal_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="mb-8">
                            <label for="notes" class="block text-sm font-medium text-gray-700">
                                Observaciones
                            </label>
                            <textarea name="notes" 
                                      id="notes" 
                                      rows="4"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('notes') border-red-300 @enderror"
                                      placeholder="Observaciones o notas adicionales sobre el propietario">{{ old('notes', $vesselOwner->notes) }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Estado Actual -->
                        <div class="mb-8 bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Estado Actual</h4>
                            <div class="flex items-center space-x-4 text-sm">
                                <span class="flex items-center">
                                    <span class="w-2 h-2 rounded-full mr-2 {{ $vesselOwner->status == 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                    Estado: {{ \App\Models\VesselOwner::STATUSES[$vesselOwner->status] }}
                                </span>
                                
                                @if($vesselOwner->tax_id_verified_at)
                                    <span class="flex items-center text-green-600">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Verificado fiscalmente
                                    </span>
                                @endif

                                <span class="text-gray-500">
                                    Última modificación: {{ $vesselOwner->updated_at->format('d/m/Y H:i') }}
                                </span>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex items-center justify-end space-x-4">
                            <a href="{{ route('company.vessel-owners.show', $vesselOwner) }}" 
                               class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-md text-sm font-medium">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Información adicional si tiene embarcaciones -->
            @if($vesselOwner->vessels->count() > 0)
            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">
                            Propietario con Embarcaciones Asociadas
                        </h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>Este propietario tiene {{ $vesselOwner->vessels->count() }} embarcación(es) asociada(s). 
                               Los cambios en la información básica podrían afectar los registros de embarcaciones.</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>