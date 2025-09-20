<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Editar País') }}: {{ $country->name }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('admin.countries.index') }}"
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Volver al Listado
                </a>
                <a href="{{ route('admin.countries.show', $country) }}"
                   class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Ver Detalle
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    {{-- Mensajes de Error --}}
                    @if ($errors->any())
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">
                                        Hay errores en el formulario
                                    </h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc list-inside space-y-1">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Formulario de Edición --}}
                    <form method="POST" action="{{ route('admin.countries.update', $country) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        {{-- Códigos Internacionales --}}
                        <div class="bg-blue-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-blue-900 mb-4">Códigos Internacionales</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Código ISO (3 letras) -->
                                <div>
                                    <label for="iso_code" class="block text-sm font-medium text-gray-700">
                                        Código ISO (3 letras) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="iso_code" 
                                           id="iso_code" 
                                           value="{{ old('iso_code', $country->iso_code) }}"
                                           required
                                           maxlength="3"
                                           pattern="[A-Z]{3}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('iso_code') border-red-300 @enderror"
                                           placeholder="ARG">
                                    @error('iso_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Ej: ARG, PRY, BRA</p>
                                </div>

                                <!-- Código Alpha2 (2 letras) -->
                                <div>
                                    <label for="alpha2_code" class="block text-sm font-medium text-gray-700">
                                        Código Alpha2 (2 letras) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="alpha2_code" 
                                           id="alpha2_code" 
                                           value="{{ old('alpha2_code', $country->alpha2_code) }}"
                                           required
                                           maxlength="2"
                                           pattern="[A-Z]{2}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('alpha2_code') border-red-300 @enderror"
                                           placeholder="AR">
                                    @error('alpha2_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Ej: AR, PY, BR</p>
                                </div>

                                <!-- Código Numérico -->
                                <div>
                                    <label for="numeric_code" class="block text-sm font-medium text-gray-700">
                                        Código Numérico
                                    </label>
                                    <input type="text" 
                                           name="numeric_code" 
                                           id="numeric_code" 
                                           value="{{ old('numeric_code', $country->numeric_code) }}"
                                           maxlength="3"
                                           pattern="[0-9]{3}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('numeric_code') border-red-300 @enderror"
                                           placeholder="032">
                                    @error('numeric_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Ej: 032, 600, 076</p>
                                </div>
                            </div>
                        </div>

                        {{-- Información Básica --}}
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información Básica</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Nombre -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700">
                                        Nombre <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="name" 
                                           id="name" 
                                           value="{{ old('name', $country->name) }}"
                                           required
                                           maxlength="100"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('name') border-red-300 @enderror"
                                           placeholder="Argentina">
                                    @error('name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Nombre Oficial -->
                                <div>
                                    <label for="official_name" class="block text-sm font-medium text-gray-700">
                                        Nombre Oficial
                                    </label>
                                    <input type="text" 
                                           name="official_name" 
                                           id="official_name" 
                                           value="{{ old('official_name', $country->official_name) }}"
                                           maxlength="150"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('official_name') border-red-300 @enderror"
                                           placeholder="República Argentina">
                                    @error('official_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Nacionalidad -->
                                <div>
                                    <label for="nationality" class="block text-sm font-medium text-gray-700">
                                        Nacionalidad
                                    </label>
                                    <input type="text" 
                                           name="nationality" 
                                           id="nationality" 
                                           value="{{ old('nationality', $country->nationality) }}"
                                           maxlength="50"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('nationality') border-red-300 @enderror"
                                           placeholder="argentino">
                                    @error('nationality')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Orden de Visualización -->
                                <div>
                                    <label for="display_order" class="block text-sm font-medium text-gray-700">
                                        Orden de Visualización <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" 
                                           name="display_order" 
                                           id="display_order" 
                                           value="{{ old('display_order', $country->display_order) }}"
                                           required
                                           min="0"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('display_order') border-red-300 @enderror">
                                    @error('display_order')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Número menor aparece primero</p>
                                </div>
                            </div>
                        </div>

                        {{-- Configuración Aduanera --}}
                        <div class="bg-yellow-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-yellow-900 mb-4">Configuración Aduanera</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Código Aduanero -->
                                <div>
                                    <label for="customs_code" class="block text-sm font-medium text-gray-700">
                                        Código Aduanero
                                    </label>
                                    <input type="text" 
                                           name="customs_code" 
                                           id="customs_code" 
                                           value="{{ old('customs_code', $country->customs_code) }}"
                                           maxlength="10"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('customs_code') border-red-300 @enderror">
                                    @error('customs_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Código SENASA -->
                                <div>
                                    <label for="senasa_code" class="block text-sm font-medium text-gray-700">
                                        Código SENASA
                                    </label>
                                    <input type="text" 
                                           name="senasa_code" 
                                           id="senasa_code" 
                                           value="{{ old('senasa_code', $country->senasa_code) }}"
                                           maxlength="10"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('senasa_code') border-red-300 @enderror">
                                    @error('senasa_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Formato de Documento -->
                                <div>
                                    <label for="document_format" class="block text-sm font-medium text-gray-700">
                                        Formato de Documento
                                    </label>
                                    <input type="text" 
                                           name="document_format" 
                                           id="document_format" 
                                           value="{{ old('document_format', $country->document_format) }}"
                                           maxlength="50"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('document_format') border-red-300 @enderror"
                                           placeholder="[0-9]{11}">
                                    @error('document_format')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Regex para validar documentos</p>
                                </div>
                            </div>
                        </div>

                        {{-- Configuración Regional --}}
                        <div class="bg-green-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-green-900 mb-4">Configuración Regional</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Código de Moneda -->
                                <div>
                                    <label for="currency_code" class="block text-sm font-medium text-gray-700">
                                        Código de Moneda
                                    </label>
                                    <input type="text" 
                                           name="currency_code" 
                                           id="currency_code" 
                                           value="{{ old('currency_code', $country->currency_code) }}"
                                           maxlength="3"
                                           pattern="[A-Z]{3}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('currency_code') border-red-300 @enderror"
                                           placeholder="ARS">
                                    @error('currency_code')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Ej: ARS, PYG, USD</p>
                                </div>

                                <!-- Zona Horaria -->
                                <div>
                                    <label for="timezone" class="block text-sm font-medium text-gray-700">
                                        Zona Horaria
                                    </label>
                                    <input type="text" 
                                           name="timezone" 
                                           id="timezone" 
                                           value="{{ old('timezone', $country->timezone) }}"
                                           maxlength="50"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('timezone') border-red-300 @enderror"
                                           placeholder="America/Argentina/Buenos_Aires">
                                    @error('timezone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Idioma Principal -->
                                <div>
                                    <label for="primary_language" class="block text-sm font-medium text-gray-700">
                                        Idioma Principal
                                    </label>
                                    <input type="text" 
                                           name="primary_language" 
                                           id="primary_language" 
                                           value="{{ old('primary_language', $country->primary_language) }}"
                                           maxlength="5"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('primary_language') border-red-300 @enderror"
                                           placeholder="es-AR">
                                    @error('primary_language')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-gray-500">Ej: es-AR, es-PY, pt-BR</p>
                                </div>
                            </div>
                        </div>

                        {{-- Configuración Operacional --}}
                        <div class="bg-purple-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-purple-900 mb-4">Configuración Operacional</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <!-- Permite Importación -->
                                <div class="flex items-center">
                                    <input id="allows_import" 
                                           name="allows_import" 
                                           type="checkbox" 
                                           {{ old('allows_import', $country->allows_import) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="allows_import" class="ml-2 text-sm font-medium text-gray-700">
                                        Permite Importación
                                    </label>
                                </div>

                                <!-- Permite Exportación -->
                                <div class="flex items-center">
                                    <input id="allows_export" 
                                           name="allows_export" 
                                           type="checkbox" 
                                           {{ old('allows_export', $country->allows_export) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="allows_export" class="ml-2 text-sm font-medium text-gray-700">
                                        Permite Exportación
                                    </label>
                                </div>

                                <!-- Permite Tránsito -->
                                <div class="flex items-center">
                                    <input id="allows_transit" 
                                           name="allows_transit" 
                                           type="checkbox" 
                                           {{ old('allows_transit', $country->allows_transit) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="allows_transit" class="ml-2 text-sm font-medium text-gray-700">
                                        Permite Tránsito
                                    </label>
                                </div>

                                <!-- Requiere Visa -->
                                <div class="flex items-center">
                                    <input id="requires_visa" 
                                           name="requires_visa" 
                                           type="checkbox" 
                                           {{ old('requires_visa', $country->requires_visa) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="requires_visa" class="ml-2 text-sm font-medium text-gray-700">
                                        Requiere Visa
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Estado y Configuración --}}
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Estado y Configuración</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Activo -->
                                <div class="flex items-center">
                                    <input id="active" 
                                           name="active" 
                                           type="checkbox" 
                                           {{ old('active', $country->active) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="active" class="ml-2 text-sm font-medium text-gray-700">
                                        País Activo
                                    </label>
                                    <p class="ml-2 text-xs text-gray-500">Disponible en listados y selecciones</p>
                                </div>

                                <!-- Es Principal -->
                                <div class="flex items-center">
                                    <input id="is_primary" 
                                           name="is_primary" 
                                           type="checkbox" 
                                           {{ old('is_primary', $country->is_primary) ? 'checked' : '' }}
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="is_primary" class="ml-2 text-sm font-medium text-gray-700">
                                        País Principal
                                    </label>
                                    <p class="ml-2 text-xs text-gray-500">AR y PY son países principales</p>
                                </div>
                            </div>
                        </div>

                        {{-- Botones de Acción --}}
                        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                            <a href="{{ route('admin.countries.index') }}"
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                Cancelar
                            </a>
                            
                            <div class="flex space-x-3">
                                <button type="submit"
                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Actualizar País
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>