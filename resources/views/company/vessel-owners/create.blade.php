<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Nuevo Propietario de Embarcación') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Registrar un nuevo propietario de embarcaciones
                </p>
            </div>
            <a href="{{ route('company.vessel-owners.index') }}" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver a Lista
            </a>
        </div>
    </x-slot>

    

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- mensajes de error o éxito -->
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Éxito!</strong>
                    <span class="block sm:inline">{{ session('success') }}</span>
                    <span class="absolute top-0 bottom-0 right-0 px-4   py-3">
                        <svg class="fill-current h-6 w-6 text-green-500" role="button" viewBox="0 0 20 20">
                            <title>Cerrar</title>
                            <path d="M14.348 5.652a1 1 0 00-1.414-1.414L10 8.586 7.066 5.652a1 1 0 00-1.414 1.414L8.586 10l-2.934 2.934a1 1 0 001.414 1.414L10 11.414l2.934 2.934a1 1 0 001.414-1.414L11.414 10l2.934-2.934z"/>
                        </svg>
                    </span>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <svg class="fill-current h-6 w-6 text-red-500" role="button" viewBox="0 0 20 20">
                            <title>Cerrar</title>
                            <path d="M14.348 5.652a1 1 0 00-1.414-1.414L10 8.586 7.066 5.652a1 1 0 00-1.414 1.414L8.586 10l-2.934 2.934a1 1 0 001.414 1.414L10 11.414l2.934 2.934a1 1 0 001.414-1.414L11.414 10l2.934-2.934z"/>
                        </svg>
                    </span>
                </div>
            @endif
            
            <!-- Formulario -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('company.vessel-owners.store') }}">
                        @csrf

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
                                           value="{{ old('tax_id') }}"
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
                                            <option value="{{ $id }}" {{ old('country_id') == $id ? 'selected' : '' }}>
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
                                           value="{{ old('legal_name') }}"
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
                                           value="{{ old('commercial_name') }}"
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
                                        <option value="O" {{ old('transportista_type') == 'O' ? 'selected' : '' }}>O - Operador</option>
                                        <option value="R" {{ old('transportista_type') == 'R' ? 'selected' : '' }}>R - Representante</option>
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
                                               {{ old('webservice_authorized') ? 'checked' : '' }}
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
                                           value="{{ old('email') }}"
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
                                           value="{{ old('phone') }}"
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
                                              placeholder="Dirección completa">{{ old('address') }}</textarea>
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
                                           value="{{ old('city') }}"
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
                                           value="{{ old('postal_code') }}"
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
                                      placeholder="Observaciones o notas adicionales sobre el propietario">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Botones -->
                        <div class="flex items-center justify-end space-x-4">
                            <a href="{{ route('company.vessel-owners.index') }}" 
                               class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-md text-sm font-medium">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Crear Propietario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>