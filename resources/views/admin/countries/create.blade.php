<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Nuevo País') }}
            </h2>
            <a href="{{ route('admin.countries.index') }}"
               class="px-3 py-2 rounded-md border text-sm text-gray-700 hover:bg-gray-50">
                ← {{ __('Volver') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Mensajes de error --}}
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-md mb-6">
                    <h3 class="font-semibold">Errores encontrados:</h3>
                    <ul class="list-disc pl-5 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Formulario Principal --}}
            <div class="bg-white rounded-xl shadow-sm">
                <form method="POST" action="{{ route('admin.countries.store') }}" class="space-y-6 p-6">
                    @csrf
                    
                    {{-- Header del formulario --}}
                    <div class="border-b border-gray-100 pb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Información del País</h3>
                        <p class="text-sm text-gray-500 mt-1">Configure los datos del país. Los campos marcados con * son obligatorios.</p>
                    </div>

                    {{-- Grid de campos principales --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        
                        {{-- Códigos Internacionales --}}
                        <div class="lg:col-span-3">
                            <h4 class="text-md font-medium text-gray-800 mb-4">Códigos Internacionales</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="iso_code" class="block text-sm font-medium text-gray-700 mb-1">
                                        Código ISO (3) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           id="iso_code" 
                                           name="iso_code"
                                           value="{{ old('iso_code') }}"
                                           maxlength="3"
                                           placeholder="ARG"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full @error('iso_code') border-red-300 @enderror"
                                           required>
                                    @error('iso_code')
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div>
                                    <label for="alpha2_code" class="block text-sm font-medium text-gray-700 mb-1">
                                        Código Alpha2 <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           id="alpha2_code" 
                                           name="alpha2_code"
                                           value="{{ old('alpha2_code') }}"
                                           maxlength="2"
                                           placeholder="AR"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full @error('alpha2_code') border-red-300 @enderror"
                                           required>
                                    @error('alpha2_code')
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div>
                                    <label for="numeric_code" class="block text-sm font-medium text-gray-700 mb-1">
                                        Código Numérico
                                    </label>
                                    <input type="text" 
                                           id="numeric_code" 
                                           name="numeric_code"
                                           value="{{ old('numeric_code') }}"
                                           maxlength="3"
                                           placeholder="032"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                </div>
                            </div>
                        </div>

                        {{-- Información Básica --}}
                        <div class="lg:col-span-3">
                            <h4 class="text-md font-medium text-gray-800 mb-4">Información Básica</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           id="name" 
                                           name="name"
                                           value="{{ old('name') }}"
                                           maxlength="100"
                                           placeholder="Argentina"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full @error('name') border-red-300 @enderror"
                                           required>
                                    @error('name')
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div>
                                    <label for="official_name" class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre Oficial
                                    </label>
                                    <input type="text" 
                                           id="official_name" 
                                           name="official_name"
                                           value="{{ old('official_name') }}"
                                           maxlength="150"
                                           placeholder="República Argentina"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                </div>

                                <div>
                                    <label for="nationality" class="block text-sm font-medium text-gray-700 mb-1">
                                        Nacionalidad
                                    </label>
                                    <input type="text" 
                                           id="nationality" 
                                           name="nationality"
                                           value="{{ old('nationality') }}"
                                           maxlength="50"
                                           placeholder="argentino"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                </div>

                                <div>
                                    <label for="display_order" class="block text-sm font-medium text-gray-700 mb-1">
                                        Orden de Visualización <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" 
                                           id="display_order" 
                                           name="display_order"
                                           value="{{ old('display_order', 999) }}"
                                           min="0"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full @error('display_order') border-red-300 @enderror"
                                           required>
                                    @error('display_order')
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Configuración Aduanera --}}
                        <div class="lg:col-span-3">
                            <h4 class="text-md font-medium text-gray-800 mb-4">Configuración Aduanera</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="customs_code" class="block text-sm font-medium text-gray-700 mb-1">
                                        Código Aduanero
                                    </label>
                                    <input type="text" 
                                           id="customs_code" 
                                           name="customs_code"
                                           value="{{ old('customs_code') }}"
                                           maxlength="10"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                </div>

                                <div>
                                    <label for="senasa_code" class="block text-sm font-medium text-gray-700 mb-1">
                                        Código SENASA
                                    </label>
                                    <input type="text" 
                                           id="senasa_code" 
                                           name="senasa_code"
                                           value="{{ old('senasa_code') }}"
                                           maxlength="10"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                </div>

                                <div>
                                    <label for="document_format" class="block text-sm font-medium text-gray-700 mb-1">
                                        Formato Documento
                                    </label>
                                    <input type="text" 
                                           id="document_format" 
                                           name="document_format"
                                           value="{{ old('document_format') }}"
                                           maxlength="50"
                                           placeholder="##-########-#"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                </div>
                            </div>
                        </div>

                        {{-- Configuración Regional --}}
                        <div class="lg:col-span-3">
                            <h4 class="text-md font-medium text-gray-800 mb-4">Configuración Regional</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="currency_code" class="block text-sm font-medium text-gray-700 mb-1">
                                        Código Moneda
                                    </label>
                                    <input type="text" 
                                           id="currency_code" 
                                           name="currency_code"
                                           value="{{ old('currency_code') }}"
                                           maxlength="3"
                                           placeholder="ARS"
                                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                </div>

                                <div>
                                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">
                                        Zona Horaria
                                    </label>
                                    <select id="timezone" 
                                            name="timezone"
                                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                        <option value="">Seleccionar zona horaria</option>
                                        <option value="America/Argentina/Buenos_Aires" {{ old('timezone') === 'America/Argentina/Buenos_Aires' ? 'selected' : '' }}>Argentina/Buenos Aires</option>
                                        <option value="America/Asuncion" {{ old('timezone') === 'America/Asuncion' ? 'selected' : '' }}>Paraguay/Asunción</option>
                                        <option value="America/Sao_Paulo" {{ old('timezone') === 'America/Sao_Paulo' ? 'selected' : '' }}>Brasil/São Paulo</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="primary_language" class="block text-sm font-medium text-gray-700 mb-1">
                                        Idioma Principal
                                    </label>
                                    <select id="primary_language" 
                                            name="primary_language"
                                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                        <option value="">Seleccionar idioma</option>
                                        <option value="es" {{ old('primary_language') === 'es' ? 'selected' : '' }}>Español</option>
                                        <option value="pt" {{ old('primary_language') === 'pt' ? 'selected' : '' }}>Portugués</option>
                                        <option value="gn" {{ old('primary_language') === 'gn' ? 'selected' : '' }}>Guaraní</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Configuración Operacional --}}
                        <div class="lg:col-span-3">
                            <h4 class="text-md font-medium text-gray-800 mb-4">Configuración Operacional</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="allows_import" 
                                           name="allows_import"
                                           value="1"
                                           {{ old('allows_import', true) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <label for="allows_import" class="ml-2 text-sm text-gray-700">
                                        Permite Importación
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="allows_export" 
                                           name="allows_export"
                                           value="1"
                                           {{ old('allows_export', true) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <label for="allows_export" class="ml-2 text-sm text-gray-700">
                                        Permite Exportación
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="allows_transit" 
                                           name="allows_transit"
                                           value="1"
                                           {{ old('allows_transit', true) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <label for="allows_transit" class="ml-2 text-sm text-gray-700">
                                        Permite Tránsito
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="requires_visa" 
                                           name="requires_visa"
                                           value="1"
                                           {{ old('requires_visa') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <label for="requires_visa" class="ml-2 text-sm text-gray-700">
                                        Requiere Visa
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Estado y Configuración Final --}}
                        <div class="lg:col-span-3">
                            <h4 class="text-md font-medium text-gray-800 mb-4">Estado</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="active" 
                                           name="active"
                                           value="1"
                                           {{ old('active', true) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <label for="active" class="ml-2 text-sm text-gray-700">
                                        Activo
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="is_primary" 
                                           name="is_primary"
                                           value="1"
                                           {{ old('is_primary') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <label for="is_primary" class="ml-2 text-sm text-gray-700">
                                        País Principal (AR/PY)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Botones de acción --}}
                    <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            <span class="text-red-500">*</span> Campos obligatorios
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.countries.index') }}" 
                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Cancelar
                            </a>
                            
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Crear País
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Información adicional --}}
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Información sobre códigos</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>ISO Code:</strong> Código de 3 letras (ARG, PRY, BRA)</li>
                                <li><strong>Alpha2 Code:</strong> Código de 2 letras (AR, PY, BR)</li>
                                <li><strong>Código Numérico:</strong> Código numérico ISO (032 para Argentina)</li>
                                <li><strong>País Principal:</strong> Solo marcar para Argentina y Paraguay</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>