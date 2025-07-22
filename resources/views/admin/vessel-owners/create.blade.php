<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Crear Propietario de Embarcaciones') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Breadcrumb -->
            <div class="mb-6">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-4">
                        <li>
                            <a href="{{ route('admin.dashboard') }}" class="text-gray-400 hover:text-gray-500">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </li>
                        <li>
                            <a href="{{ route('admin.vessel-owners.index') }}" class="text-gray-400 hover:text-gray-500">
                                Propietarios
                            </a>
                        </li>
                        <li>
                            <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </li>
                        <li class="text-gray-500">
                            Crear
                        </li>
                    </ol>
                </nav>
            </div>

            <form method="POST" action="{{ route('admin.vessel-owners.store') }}" class="space-y-6">
                @csrf

                <!-- Información Básica -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Información Básica</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Datos fundamentales del propietario de embarcaciones.
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Empresa (DIFERENCIA CLAVE: Admin puede elegir empresa) -->
                            <div class="md:col-span-2">
                                <label for="company_id" class="block text-sm font-medium text-gray-700">
                                    Empresa <span class="text-red-500">*</span>
                                </label>
                                <select name="company_id" 
                                        id="company_id"
                                        required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('company_id') border-red-300 @enderror">
                                    <option value="">Seleccionar empresa...</option>
                                    @foreach($companies as $id => $name)
                                        <option value="{{ $id }}" {{ old('company_id') == $id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('company_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- CUIT/RUC -->
                            <div>
                                <label for="tax_id" class="block text-sm font-medium text-gray-700">
                                    CUIT/RUC <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="tax_id" 
                                       id="tax_id"
                                       value="{{ old('tax_id') }}"
                                       required
                                       maxlength="15"
                                       placeholder="20123456789"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('tax_id') border-red-300 @enderror">
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
                                        required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('country_id') border-red-300 @enderror">
                                    <option value="">Seleccionar país...</option>
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
                            <div>
                                <label for="legal_name" class="block text-sm font-medium text-gray-700">
                                    Razón Social <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       name="legal_name" 
                                       id="legal_name"
                                       value="{{ old('legal_name') }}"
                                       required
                                       maxlength="200"
                                       placeholder="Empresa de Transportes S.A."
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('legal_name') border-red-300 @enderror">
                                @error('legal_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Nombre Comercial -->
                            <div>
                                <label for="commercial_name" class="block text-sm font-medium text-gray-700">
                                    Nombre Comercial
                                </label>
                                <input type="text" 
                                       name="commercial_name" 
                                       id="commercial_name"
                                       value="{{ old('commercial_name') }}"
                                       maxlength="200"
                                       placeholder="Transportes FluvialTrans"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('commercial_name') border-red-300 @enderror">
                                @error('commercial_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Tipo de Transportista -->
                            <div>
                                <label for="transportista_type" class="block text-sm font-medium text-gray-700">
                                    Tipo de Transportista <span class="text-red-500">*</span>
                                </label>
                                <select name="transportista_type" 
                                        id="transportista_type"
                                        required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('transportista_type') border-red-300 @enderror">
                                    <option value="">Seleccionar tipo...</option>
                                    <option value="O" {{ old('transportista_type') === 'O' ? 'selected' : '' }}>
                                        Operador
                                    </option>
                                    <option value="R" {{ old('transportista_type') === 'R' ? 'selected' : '' }}>
                                        Representante
                                    </option>
                                </select>
                                @error('transportista_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Estado -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado <span class="text-red-500">*</span>
                                </label>
                                <select name="status" 
                                        id="status"
                                        required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('status') border-red-300 @enderror">
                                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>
                                        Activo
                                    </option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>
                                        Inactivo
                                    </option>
                                    <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>
                                        Suspendido
                                    </option>
                                    <option value="pending_verification" {{ old('status') === 'pending_verification' ? 'selected' : '' }}>
                                        Pendiente Verificación
                                    </option>
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Información de Contacto</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Datos de contacto y ubicación del propietario.
                            </p>
                        </div>
                        
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
                                       placeholder="contacto@empresa.com"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('email') border-red-300 @enderror">
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
                                       placeholder="+54 11 4567-8900"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('phone') border-red-300 @enderror">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Dirección -->
                            <div class="md:col-span-2">
                                <label for="address" class="block text-sm font-medium text-gray-700">
                                    Dirección
                                </label>
                                <input type="text" 
                                       name="address" 
                                       id="address"
                                       value="{{ old('address') }}"
                                       placeholder="Av. del Puerto 1234"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('address') border-red-300 @enderror">
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
                                       placeholder="Buenos Aires"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('city') border-red-300 @enderror">
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
                                       placeholder="C1001"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('postal_code') border-red-300 @enderror">
                                @error('postal_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuración de Webservices -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Configuración de Webservices</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Permisos y configuración para uso de webservices gubernamentales.
                            </p>
                        </div>
                        
                        <div class="space-y-4">
                            <!-- Autorización Webservices -->
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="webservice_authorized" 
                                           name="webservice_authorized" 
                                           type="checkbox" 
                                           value="1"
                                           {{ old('webservice_authorized') ? 'checked' : '' }}
                                           class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="webservice_authorized" class="font-medium text-gray-700">
                                        Autorizado para Webservices
                                    </label>
                                    <p class="text-gray-500">
                                        Permitir que este propietario realice operaciones a través de webservices gubernamentales (AFIP, DNA, etc.).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Observaciones</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Notas adicionales sobre el propietario.
                            </p>
                        </div>
                        
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">
                                Notas
                            </label>
                            <textarea name="notes" 
                                      id="notes" 
                                      rows="4"
                                      placeholder="Observaciones adicionales sobre el propietario..."
                                      class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm @error('notes') border-red-300 @enderror">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.vessel-owners.index') }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Crear Propietario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript para validaciones en tiempo real -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validación de CUIT/RUC en tiempo real
        const taxIdInput = document.getElementById('tax_id');
        taxIdInput.addEventListener('input', function() {
            // Remover caracteres no numéricos
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Ayuda contextual para tipo de transportista
        const transportistaSelect = document.getElementById('transportista_type');
        transportistaSelect.addEventListener('change', function() {
            const helpTexts = {
                'O': 'Operador: Empresa que opera directamente las embarcaciones.',
                'R': 'Representante: Empresa que representa a propietarios de embarcaciones.'
            };
            
            // Remover ayuda anterior
            const existingHelp = document.getElementById('transportista-help');
            if (existingHelp) {
                existingHelp.remove();
            }
            
            if (this.value && helpTexts[this.value]) {
                const helpDiv = document.createElement('div');
                helpDiv.id = 'transportista-help';
                helpDiv.className = 'mt-1 text-sm text-blue-600';
                helpDiv.textContent = helpTexts[this.value];
                this.parentNode.appendChild(helpDiv);
            }
        });
    });
    </script>
</x-app-layout>